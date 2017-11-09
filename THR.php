<?php

require_once realpath(__DIR__ . '/application/vendor/autoload.php');

/**
 * TODO: Expliciting loading widely used classes here. Eventually we should autoload them.
 */
require_once realpath(__DIR__ . '/application/core/THR/Exception/THR_Exception.php');
require_once realpath(__DIR__ . '/application/core/THR/Exception/Core_Exception.php');
require_once realpath(__DIR__ . '/application/core/THR/Exception/API_Exception.php');
require_once realpath(__DIR__ . '/application/core/THR/Exception/API_Authorization_Exception.php');
require_once realpath(__DIR__ . '/application/core/THR/Exception/API_NotFound_Exception.php');
require_once realpath(__DIR__ . '/application/core/THR/Exception/API_BadRequest_Exception.php');
require_once realpath(__DIR__ . '/application/core/THR/Exception/DataIntegrityException.php');

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\SessionHandler;
use Doctrine\ORM\EntityManager;
use Throne\Entity\Company;
use THR\Exception\API_Authorization_Exception;

abstract class THR
{
    const APP_THRONE            = 'throne';
    const APP_PULSE             = 'pulse';
    const APP_LEARN             = 'learn';

    const THINKHR_CLIENT_ID     = 8148;
    const CLIENT_TYPE_THINKHR   = 'thinkhr';
    const CLIENT_TYPE_BROKER    = 'broker';
    const CLIENT_TYPE_RE        = 're';

    /**
     * Environments, keyed by root domain.
     *
     * @var array
     */
    public static $environments = [
        'thinkhr-local.com' => 'local',
        'thinkhr-dev.com'   => 'development',
        'thinkhr-qa.com'    => 'testing',
        'thinkhr.com'       => 'production',
    ];

    /**
     * Returns true if environment is production, false otherwise.
     *
     * @return boolean
     */
    public static function isProduction()
    {
        return (self::getEnvironment() == 'production');
    }

    /**
     * Returns environment name.
     *
     * @return boolean
     */
    public static function getEnvironment()
    {
        return self::$environments[self::getRootDomain()];
    }

    /**
     * Bootstraps the session utilizing DynamoDB.
     *
     * @return void
     */
    public static function bootstrapSession($config)
    {
        $root_domain    = self::getRootDomain();
        $aws_key        = $config->item('aws_access_key');
        $aws_secret     = $config->item('aws_secret_key');
        $session_table  = $config->item('aws_dynamo_session_table');;

        setcookie($_SERVER['HTTP_HOST'], '', time() - 7200, '/');
        setcookie('previousPortalVisit', '', time() - 7200, '/');
        setcookie('PHPSESSID', '', time() - 7200, '/');
        ini_set('session.cookie_domain', '.' . $root_domain);

        $client                 = self::getDynamoDbClient($aws_key, $aws_secret);
        $sessionHandlerConfig   = [
            'table_name'        => $session_table,
            'session_lifetime'  => 3600,
        ];

        $sessionHandler = SessionHandler::fromClient($client, $sessionHandlerConfig);
        $sessionHandler->register();
        session_start();
    }

    /**
     * Instantiates and returns a DynamoDB client.
     *
     * @param string $aws_key
     * @param string $aws_secret
     * @return DynamoDbClient
     */
    public static function getDynamoDbClient($aws_key, $aws_secret)
    {
        return new DynamoDbClient([
            'credentials' => [
                'key'    => $aws_key,
                'secret' => $aws_secret,
            ],
            'http'      => [
                'verify' => false,
            ],
            'region'    => 'us-west-2',
            'version'   => 'latest',
            'endpoint'  => 'https://dynamodb.us-west-2.amazonaws.com',
            'debug'     => false,
        ]);
    }

    /**
     * Returns the current root domain.
     *
     * @return string
     */
    public static function getRootDomain()
    {
        if (!preg_match("/^.+\.(thinkhr(-local|-dev|-qa)?\.com)$/", $_SERVER['HTTP_HOST'], $matches)) {
            header('HTTP/1.1 503 Service Unavailable.', true, 503);
            echo 'The application is not registed to run with this domain.';
            exit(1);
        }

        return $matches[1];
    }

    /**
     * Generates and returns a hash.
     *
     * @param string $seed
     * @return string
     */
    public static function generateHash($seed = '')
    {
        return strtoupper(strrev(str_replace('=', '', base64_encode(str_replace(' ', '', microtime() . $seed)))));
    }

    ////////////////////////////////////////////////////////////////////////////

    /**
     * Returns authenticated user's client_type.
     *
     * @return string
     */
    public static function getClientType()
    {
        if (!isset($_SESSION['THR_AUTH_USER']['client_type'])) {
            throw new API_Authorization_Exception();
        }
        return $_SESSION['THR_AUTH_USER']['client_type'];
    }

    /**
     * Returns authenticated user's client_id.
     *
     * @DEPRECATED See self::getSessionCompanyId
     * @return integer
     */
    public static function getClientId()
    {
        return self::getSessionCompanyId();
    }

    /**
     * Returns authenticated user's broker_id.
     *
     * @DEPRECATED See self::getSessionBrokerId
     * @return integer
     */
    public static function getBrokerId()
    {
        return self::getSessionBrokerId();
    }


    /**
     * Returns authenticated user's company ID.
     *
     * @return integer
     */
    public static function getSessionCompanyId()
    {
        if (!isset($_SESSION['THR_AUTH_USER']['client_id'])) {
            throw new API_Authorization_Exception();
        }

        return (int) @$_SESSION['THR_AUTH_USER']['client_id'];
    }

    /**
     * Returns authenticated user's company's broker ID.
     *
     * @return integer
     */
    public static function getSessionBrokerId()
    {
        return (int) @$_SESSION['THR_AUTH_USER']['broker_id'];
    }

    /**
     * Returns authenticated user's thrcontactid.
     *
     * @return integer
     */
    public static function getThrContactId()
    {
        return (int) @$_SESSION['THR_AUTH_USER']['thrcontactid'];
    }

    /**
     * Returns thinkhr's client id.
     *
     * @return integer
     */
    public static function getThinkHRClientId()
    {
        return (int) self::THINKHR_CLIENT_ID;
    }

    /**
     * Returns true if authenticated user is a ThinkHR client, false otherwise.
     *
     * @return boolean
     */
    public static function isThinkHRClient()
    {
        return (self::getClientType() == self::CLIENT_TYPE_THINKHR);
    }

    /**
     * Returns true if authenticated user is a broker client, false otherwise.
     *
     * @return boolean
     */
    public static function isBrokerClient()
    {
        return (self::getClientType() == self::CLIENT_TYPE_BROKER);
    }

    /**
     * Returns true if authenticated user is a RE client, false otherwise.
     *
     * @return boolean
     */
    public static function isREClient()
    {
        return (self::getClientType() == self::CLIENT_TYPE_RE);
    }

    /**
     * Builds exception message.
     *
     * @param string $message
     * @param string $file
     * @return string
     */
    public static function buildExceptionMessage($message, $file = null, $line = null)
    {
        if (!self::isProduction()) {
            $message .= ', ' . $file . ':' . $line;
        }

        return $message;
    }

    /**
     * Return the IP of the request's client IP address.
     */
    public static function getRequestIPAddress()
    {
        if (ENVIRONMENT === 'local') {
            return $_SERVER['REMOTE_ADDR'];
        } else {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
    }

    /**
     * Makes a proxy request to API stack.
     *
     * @param array $args
     *      [
     *          'method'    => 'required',  // 'GET', 'POST', 'PUT', 'PATCH', 'DELETE'
     *          'path'      => 'required',  // e.g. /path/to/resource
     *          'query'     => 'optional',  // e.g. page=1&limit=10&order=asc
     *          'body'      => 'optional',  // array or json
     *      ]
     * @param boolean $debug
     * @return array
     *      [
     *          'body'      => array|null,  // e.g. {"id": 1}, or null
     *          'status'    => string       // e.g. 'success' or 'error'
     *      ]
     * @throws InvalidArgumentException
     */
    public static function makeProxyRequest(array $args, $debug = false)
    {
        $ci = get_instance();

        // get and validate arguments
        {
            $method = strtoupper(@$args['method']);
            $path   = @$args['path'];
            $query  = @$args['query'];
            $body   = @$args['body'];

            if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])) {
                throw new InvalidArgumentException("Invalid 'method' specified: '$method'");
            }
            if ($body && !is_array($body) && !is_string($body)) {
                throw new InvalidArgumentException("Invalid 'body' specified: '$body'");
            }
            if (is_array($body)) {
                $body = json_encode($body, JSON_HEX_APOS);
            }
            if ($body && is_string($body) && json_decode($body) === null) {
                throw new InvalidArgumentException("Invalid 'body' specified: '$body'");
            }
            if ($body && !in_array($method, ['POST', 'PUT', 'PATCH'])) {
                throw new InvalidArgumentException("Cannot specify 'body' for specified 'method': '$method'");
            }
        }

        // get session info
        $session = urlencode(json_encode([
            'clientType'    => @$_SESSION['THR_AUTH_USER']['client_type'],
            'clientId'      => @$_SESSION['THR_AUTH_USER']['client_id'],
            'brokerId'      => @$_SESSION['THR_AUTH_USER']['broker_id'],
        ]));

        // build url and get access token
        {
            $url            = 'https://' . $ci->config->item('zend_api_root') . '/' . ltrim($path, '/') . '?session=' . $session;
            $accessToken    = $ci->config->item('zend_api_access_token');

            if ($query) {
                $url .= '&' . $query;
            }
        }

        // make request
        {
            // Optionally call cURL via native cURL functions rather than using shell_exec (defaults
            // to shell_exec for all environments)
            $proxyMethod = $ci->config->item('zend_api_use_native_curl') ?
                '_doCurlProxyRequestByLib' :
                '_doCurlProxyRequestByShellExec'
            ;

            list($body, $status) = self::$proxyMethod($body, $method, $url, $accessToken);
            $status = ($status >= 200 && $status < 300) ? 'success' : 'error';

            return [json_decode($body, true), $status];
        }
    }

    private static function _doCurlProxyRequestByShellExec($body, $method, $url, $accessToken)
    {
        $args = (self::getEnvironment() == 'local') ? "-k -o - -s -w '\n%{http_code}\n'" : "-o - -s -w '\n%{http_code}\n'";
        $data = $body ? "--data '$body'" : "";

        $request = "
            curl $args \
                --request $method \
                --url '$url' \
                --header 'accept: application/json' \
                --header 'authorization: Bearer $accessToken' \
                --header 'content-type: application/json' \
                $data
        ";

        $response = explode("\n", trim(shell_exec($request), "\n"));

        if ($debug) {
            print_r([
                'REQUEST'   => $request,
                'RESPONSE'  => $response,
            ]);
            exit;
        }

        if (count($response) == 1) {
            $body   = null;
            $status = $response[0];
        } else {
            $body   = $response[0];
            $status = $response[1];
        }

        return [$body, $status];
    }

    private function _doCurlProxyRequestByLib($body, $method, $url, $accessToken)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Accept: application/json",
            "Authorization: Bearer $accessToken",
            "Content-type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  $method);

        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return [$body, $status];
    }



    ////////////////////////////////////////////////////////////////////////////

    /**
     * DEPRECATED - use BaseRepository::buildWhereOrderBy
     *
     * Builds and returns an order by clause.
     *
     * @param array $fields
     * @param string $defaultOrderBy
     * @param string $defaultSortOrder
     * @param string $order
     * @return string
     * @throws \THR_API_Exception
     */
    public static function buildOrderBy(array $fields, $defaultOrderBy, $defaultSortOrder, $order)
    {
        $orderCompontents   = explode(' ', $order);
        $orderBy            = (@$orderCompontents[0]) ?: $defaultOrderBy;

        if (!in_array($orderBy, $fields)) {
            throw new \THR_API_Exception("Invalid order by clause specified: '$order'", 400);
        }

        $sortOrder = (@$orderCompontents[1]) ? strtoupper($orderCompontents[1]) : $defaultSortOrder;

        if (!in_array($sortOrder, ['ASC', 'DESC'])) {
            throw new \THR_API_Exception("Invalid order by clause specified: '$order'", 400);
        }

        return "ORDER BY $orderBy $sortOrder";
    }

    /**
     * DEPRECATED - use BaseRepository::buildWhereOrderBy
     *
     * Builds and returns a where clause.
     *
     * @param string $searchField
     * @param string $where
     * @param array $filters
     * @param string $search
     * @return string
     */
    public static function buildWhere($searchField, $where, array $filters, $search)
    {
        // TODO: filters

        // search
        if (strlen($search)) {
            if (is_string($searchField)) {
               $where .= " AND $searchField LIKE '%$search%'";
            } elseif (is_array($searchField)) {
                if (sizeof($searchField) == 1) {
                    $where .= " AND " . $searchField[0] . " LIKE '$search%'";
                } else if (sizeof($searchField) > 1) {
                    $where .= " AND ( ";
                    foreach ($searchField as $key => $value) {
                        if ($key != 0)
                            $where .= " OR ";
                        $where .= " ( " . $searchField[$key] . " LIKE '$search%' ) ";
                    }
                    $where .= " ) ";
                }
            }
        }

        return $where ? "WHERE $where" : "";
    }

    /**
     * Finds and returns an accessible entity of the specified class type.
     * Throws a THR_API_Exception if specified entity is not accessible.
     * Note: The entity must either be a Company or have a relation to a Company
     * and have a getCompany() method.
     *
     * @param Doctrine\ORM\EntityManager $entityManger
     * @param string $entityClass
     * @param integer|array $condition
     * @return mixed
     * @throws THR_API_Exception
     * @throws THR_Exception
     */
    public static function findAccessibleEntity(Doctrine\ORM\EntityManager $em, $entityClass, $condition)
    {
        $clientType = self::getClientType();
        $clientId = self::getClientID();

        $repository = $em->getRepository($entityClass);

        if ($entityClass == Company::class) {
            if (!self::isDigits($condition['id'])) {
                throw new THR_Exception("Invalid entityClass, condition specified: '$entityClass', '$condition'");
            }
            $entity     = $repository->findOneBy($condition);
            $company    = $entity;
            $message    = "Invalid companyId specified: " . $condition['id'];

        } else {
            if (self::isDigits($condition['id'])) {
                $entity = $repository->find($condition);
            } elseif (is_array($condition)) {
                $entity = $repository->findOneBy($condition);
            } else {
                throw new THR_Exception("Invalid entityClass, condition specified: '$entityClass', '$condition'");
            }

            $company    = $entity->getCompany();
            $message    = "Entity not found.";
        }

        if (!$entity || !self::hasAccessToCompany($clientType, $clientId, $company)) {
            throw new THR_API_Exception($message, 404);
        }

        return $entity;
    }

    /**
     * Returns an access where clause restricting access based on the client type:
     *      self::CLIENT_TYPE_THINKHR  : can access any record
     *      self::CLIENT_TYPE_BROKER   : can access own company's records, or REs' records under the company
     *      self::CLIENT_TYPE_RE       : can access own company's records
     *
     * @param string $clientType
     * @param integer $clientId
     * return string
     * @throws THR_Exception
     */
    public static function getAccessWhereClause()
    {
        $clientType = THR::getClientType();
        $clientId   = THR::getClientID();

        switch ($clientType) {
            case self::CLIENT_TYPE_THINKHR:
                return "1 = 1";

            case self::CLIENT_TYPE_BROKER:
                return "c.broker = $clientId";

            case self::CLIENT_TYPE_RE:
                return "c.id = $clientId";

            default:
                throw new THR_Exception("Invalid clientType specified: '$clientType'");
        }
    }

    /**
     * Returns true if specified account has access to the specified company, false otherwise.
     *
     * @param string $accountType
     * @param integer $accountId
     * @param Application\Db\Throne\V1\Entity\Company $company
     * @return boolean
     * @throws Application\Apigility\Exception\InvalidArgumentException
     */
    public static function hasAccessToCompany($accountType, $accountId, Company $company)
    {
        switch ($accountType) {
            case self::CLIENT_TYPE_THINKHR:
                return true;

            case self::CLIENT_TYPE_BROKER:
                return ($company->getBrokerId() == $accountId);

            case self::CLIENT_TYPE_RE:
                return ($company->getId() == $accountId);

            default:
                throw new InvalidArgumentException("Invalid accountType specified: '$accountType'");
        }
    }

    /**
     * Returns true if specified value is comprised of only digits, false otherwise.
     *
     * @param mixed $value
     * @return boolean
     */
    public static function isDigits($value)
    {
        if (!is_int($value) && !is_string($value)) {
            return false;
        }
        return (bool) preg_match("/^[1-9]\d*$/", $value);
    }

}

class THR_Exception extends \Exception
{
    const CODE_PUBLIC = -1;

    /**
     * Constructor.
     *
     * @param string $message
     * @param integer $code
     * @param Throwable $previous
     * @return void
     */
    public function __construct($message = '', $code = 0, \Throwable $previous = null)
    {
        return parent::__construct($message, $code, $previous);
    }
}

class THR_API_Exception extends THR_Exception
{
    /**
     * Constructor.
     *
     * @param string $message
     * @param integer $code
     * @param Throwable $previous
     * @return void
     */
    public function __construct($message = '', $code = 0, \Throwable $previous = null)
    {
        return parent::__construct($message, $code, $previous);
    }
}
