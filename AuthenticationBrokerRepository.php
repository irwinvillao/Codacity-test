<?php

namespace Throne\Entity\Repository;

use Doctrine\ORM\Query;
use Throne\Entity\AuthenticationBroker;
use Throne\Entity\CompanyMapper;

class AuthenticationBrokerRepository extends BaseRepository
{
    /**
     * Add description
     * ...
     *
     * @param array $parameters
     * @return array
     */
    public function create($parameters)
    {
        $brokerHasAuthCode = self::read($parameters["brokerId"]);
        $result["error"]   = "";
        
        // create authentication code
        if (!isset($brokerHasAuthCode["id"])) {
            $authenticationCodeAlreadyExists = $this->readByAuthenticationCode($parameters["authenticationCode"]);
            
            // check if auth code doesn't exist
            if (!$authenticationCodeAlreadyExists) {
                $em                   = $this->getEntityManager();
                $authenticationBroker = new AuthenticationBroker();
                $save                 = self::save($authenticationBroker, $parameters);
                $result               = array_merge($result, $save);
                
            } else {
                $result["error"] = "already_exists"; //Authentication code already exists
            }

        } else {
            $result["error"] = "has_one_authentication_code"; //Authentication code already exists
        }
            
        return $result;
    }


    /**
     * Add description
     * ...
     *
     * @param array $parameters
     * @return array
     */
    public function update($parameters)
    {
        $brokerHasAuthCode = self::read($parameters["brokerId"]);
        $result["error"]   = "";
        
        if (isset($brokerHasAuthCode["id"])) {
            $em = $this->getEntityManager();
            $authenticationCodeAlready = self::readByAuthenticationCode($parameters["authenticationCode"], $parameters["brokerId"]);
            
            if (!isset($authenticationCodeAlready["id"])) {
                $authenticationBroker = $em->getRepository('Throne\Entity\AuthenticationBroker')->find($brokerHasAuthCode["id"]);
                $save                 = self::save($authenticationBroker, $parameters);
                $result               = array_merge($result, $save);
            } else {
                $result["error"] = "has_authentication_code"; //other broker has this authentication code
            }

        } else {
            $result["error"] = "create_authentication_code"; //you need to create authentication code
        }

        return $result;
    }

    /**
     * Add description
     * ...
     *
     * @param object $authenticationBroker
     * @param array $parameters
     * @return array
     */
    private function save($authenticationBroker, $parameters)
    {
        $em = $this->getEntityManager();
        $this->updateFromArray($authenticationBroker, $parameters, $this->getMethodsAuthenticationBroker(), false);
        $em->persist($authenticationBroker);
        $em->flush();
        
        $result["AuthenticationBroker"] = [
            'authenticationId'   => $authenticationBroker->getId(),
            'authenticationCode' => $authenticationBroker->getAuthenticationCode(),
            'brokerId'           => $authenticationBroker->getBrokerId()
        ];

        return $result;
    }


    /**
     * Add description
     * ...
     *
     * @param string $authenticationCode
     * @param integer $brokerId
     * @return array
     */
    public function readByAuthenticationCode($authenticationCode, $brokerId = NULL)
    {
        $brokerClause = ($brokerId > 0) ? " AND a.brokerId !=:brokerId " : "";
        
        $dql = "
            SELECT a
            FROM Throne\Entity\AuthenticationBroker a
            WHERE a.authenticationCode = :authenticationCode
            $brokerClause
        ";
        
        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setHint(Query::HINT_INCLUDE_META_COLUMNS, true)
            ->setParameter('authenticationCode', $authenticationCode)
        ;
        
        if ($brokerClause) $query->setParameter('brokerId', $brokerId);

        return (array) @$query->getArrayResult()[0];
    }

    /**
     * Add description
     * ...
     *
     * @param integer $brokerId
     * @return array
     */
    public function read($brokerId)
    {
        $whereOrderBy = $this->buildWhereOrderBy([
            "conditions" => ["a.brokerId = :brokerId"]
        ]);
        $dql = "
            SELECT a
            FROM Throne\Entity\AuthenticationBroker a
            WHERE $whereOrderBy
        ";
        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setHint(Query::HINT_INCLUDE_META_COLUMNS, true)
            ->setParameter('brokerId', $brokerId)
        ;

        return (array) @$query->getArrayResult()[0];
    }    

    /**
     * Add description
     * ...
     *
     * @param array $parameters
     * @param string $ssoEncryptionKey
     * @return array
     */    
    public function validateAuthenticationCodeGetAccount($parameters, $ssoEncryptionKey = NULL)
    {
        $result["success"]   = false;
        $conditionDomain     = "";
        $domain              = "";
        $data["referer"]     = "";
        $data["pregReferer"] = "";
        $data["domain"]      = "";
        $resultBuildWhere    = self::buildWhereSSO($parameters, $ssoEncryptionKey);
        $domain              = trim($resultBuildWhere["domain"]);
        $data["domain"]      = $domain;
        
        if (($domain) && ($parameters["email"]) && ($parameters["authenticationCode"])) {

            $dql = "
                SELECT a.brokerId, a.ssoMapper 
                FROM Throne\Entity\AuthenticationBroker a
                WHERE a.authenticationCode = '" . $parameters["authenticationCode"] . "'
                AND (a.url like '%$domain%' " . $resultBuildWhere["condition"] . ")
                AND a.isEnabled = '1'
            ";

            $query = $this->getEntityManager()
                ->createQuery($dql)
                ->setHint(Query::HINT_INCLUDE_META_COLUMNS, true)
                //->setParameter($params)
            ;

            $result = (array) $query->getArrayResult();
            $result = @$result[0];
            if (
                (isset($result["brokerId"])) && 
                ($result["brokerId"] > 0)
            ) {
                $result["success"]    = true;
                $resultSsoCompanyName = $this->validateSsoCompanyName($parameters["email"]);
                $result               = array_merge($resultSsoCompanyName, $result);
                if (
                    ($result["success"]) &&
                    ($result["companyId"] > 0)
                ) {
                    $dataCompanyMapper = self::getCompanyMapper($result["companyId"]);
                    if (
                        (isset($dataCompanyMapper["ssoMapper"])) &&
                        ($dataCompanyMapper["ssoMapper"] != "")
                    ){
                        $result["ssoMapper"] = $dataCompanyMapper["ssoMapper"];
                    }
                }
            } else {
                $result["error"] = "no_valid_authentication_code"; 
            }
        } else {
            if (!$domain) {
                $result["error"] = "missing_origin";
            } elseif (!$parameters["email"]) {
                $result["error"] = "missing_email";
            } elseif (!$parameters["authenticationCode"]) {
                $result["error"] = "missing_authentication_code";
            }
        }
        $result["log"] = $resultBuildWhere["logData"];

        return $result;
    }

    /**
     * Add description
     * ...
     *
     * @param string $ssoId
     * @return array
     */  
    private function validateSsoCompanyName($ssoId)
    {
        $result["ssoClient"] = "";
        $result["email"]     = "";
        $arrayEmail          = (strpos($ssoId, ":") !== false ) ? explode(":", $ssoId) : $ssoId;

        if (is_array($arrayEmail)) {
            $result["ssoClient"] = ($arrayEmail[0] != "") ? trim($arrayEmail[0]) : "";
            $result["email"]     = trim($arrayEmail[1]);
            if ($result["ssoClient"]) {
                $dataClient = self::getSsoCompanyName($result["ssoClient"]);
                if (isset($dataClient["companyId"])) {
                    $result["companyId"] = $dataClient["companyId"];
                } else {
                    $result["error"]   = "invalid_sso_client";
                    $result["success"] = false;
                }
            }
        } else {
            $result["email"] = $ssoId;
        }

        return $result;
    }

    /**
     * Add description
     * ...
     *
     * @param string $ssoCompanyName
     * @return array
     */
    public function getSsoCompanyName($ssoCompanyName)
    {
        $dql = "
            SELECT a.ssoCompanyName, a.id ,a.companyId
            FROM Throne\Entity\CompanyMapper a
            WHERE a.ssoCompanyName = '" . strtolower($ssoCompanyName) . "'
            AND a.ssoCompanyName IS NOT NULL
        ";
        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setHint(Query::HINT_INCLUDE_META_COLUMNS, true)
            ->setMaxResults(1);
        ;
        
        return (array) @$query->getArrayResult()[0];
    }

    /**
     * Add description
     * ...
     *
     * @param integer $companyId
     * @return array
     */
    public function getCompanyMapper($companyId)
    {
        $conditions = "a.companyId = :companyId";
        $parameters = ["companyId" => $companyId];
        $dql = "
            SELECT a.ssoMapper
            FROM Throne\Entity\CompanyMapper a
            WHERE $conditions
            AND a.status = 1
        ";
        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setHint(Query::HINT_INCLUDE_META_COLUMNS, true)
            ->setMaxResults(1)
            ->setParameters($parameters)
        ;

        return (array) @$query->getArrayResult()[0];
    }

    /**
     * Add description
     * ...
     *
     * @param string $ssoEncryptionKey
     * @param string $value
     * @return array
     */
    private function decodeSecret($ssoEncryptionKey, $value) 
    {
        $value     = $value;
        $value     = str_replace("@", "+", $value);
        $value     = str_replace(" ", "+", $value);
        $decrypted = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5(base64_decode($ssoEncryptionKey)), base64_decode($value), MCRYPT_MODE_CBC, md5(md5(base64_decode($ssoEncryptionKey)))), "\0");
        return $decrypted;
    }

    /**
     * Add description
     * ...
     *
     * @param string $token
     * @param string $ssoEncryptionKey
     * @return array
     */
    public function verifySSOToken($token, $ssoEncryptionKey)
    {
        $result["error"] = "";
        $decryptToken    = self::decodeSecret($ssoEncryptionKey, $token);
        $arrayDataToken  = explode("|", $decryptToken);
        if (
            (is_array($arrayDataToken)) &&
            (sizeof($arrayDataToken) > 0)
        ) {
            $uniqid      = $arrayDataToken[0];
            $timecode    = 1;
            $mkdateold   = $arrayDataToken[5];
            $mkdateold   = $mkdateold + ($timecode * 3000);
            $mkdatenow   = gmmktime(gmdate("H"), gmdate("i"), gmdate("s"), gmdate("m"), gmdate("d"), gmdate("Y"));
            $searchterm  = $arrayDataToken[6];
            $urlsearch   = $arrayDataToken[7];
            $success = 0;
            if ($mkdatenow <= $mkdateold) {
                $result["email"]    = $arrayDataToken[1];
                $result["brokerId"] = $arrayDataToken[2];
                $result["userId"]   = $arrayDataToken[3];
                $result["clientId"] = $arrayDataToken[4];
            } else {
                $result["error"] = "token_expired";
            }
        } else {
            $result["error"] = "no_valid_token";
        }

        return $result;
    }

    /**
     * Add description
     * ...
     *
     * @param array $parameters
     * @param string $ssoEncryptionKey
     * @return array
     */
    private function buildWhereSSO($parameters, $ssoEncryptionKey)
    {
        $domain          = "";
        $conditionDomain = "";
        {   //if the call to the service was from thinkhr and a token has been received, it must be decrypted
            if (
                ($parameters["originEncrypted"]) &&
                ($parameters["host"])
            ) {
                $originDecryted = self::decodeSecret($ssoEncryptionKey, $parameters["originEncrypted"]);
                $array_domain   = explode("|", $originDecryted);
                if (sizeof($array_domain) >= 1) {
                    unset($array_domain[sizeof($array_domain)-1]);
                    foreach ($array_domain as $key => $value) {
                        switch($key){
                            case 0:
                                $parameters["originDomain"] = $value;
                                $data["referer"]            = $value;
                            break;
                            case 1:
                                $parameters["pregOrigin"]   = $value;
                                $data["pregReferer"]        = $value;
                            break;
                        }
                    }
                    $data["originEncrypted"] = $originEncrypted;
                }
            }
        }

        {   //set the condition to validate if the origin of the call is allowed
            if ($parameters["originDomain"]) {
                $conditionDomain = " or a.url like '%" . $parameters["originDomain"] . "%' ";
                $domain          = $parameters["originDomain"];
                $data["referer"] = $parameters["originDomain"];
            }
            if ($pararameters["refererDomain"]) {
                $conditionDomain = " or a.url like '%" . $pararameters["refererDomain"] . "%' ";
                $domain          = ($domain == "") ? $pararameters["refererDomain"] : $domain;
                $data["referer"] = $pararameters["refererDomain"];
            }
            if ($parameters["pregOrigin"]) {
                $conditionDomain     = " or a.url like '%" . $parameters["pregOrigin"] . "%' ";
                $domain              = ($domain == "") ? $parameters["pregOrigin"] : $domain;
                $data["pregReferer"] = $parameters["pregOrigin"];
            }
            if ($parameters["pregReferer"]) {
                $conditionDomain     = " or a.url like '%" . $parameters["pregReferer"] . "%' ";
                $domain              = ($domain == "") ? $parameters["pregReferer"] : $domain;
                $data["pregReferer"] = $parameters["pregReferer"];
            }
        }
        $data["domain"]      = $domain;
        $result["condition"] = $conditionDomain;
        $result["logData"]   = $data;
        $result["domain"]    = $domain;

        return $result;
    }

    /**
     * Add description
     * ...
     *
     * @return array
     */
    private function getMethodsAuthenticationBroker()
    {
        $methods = [
            'authenticationCode' => 'setAuthenticationCode',
            'url'                => 'setUrl',
            'createdByUser'      => 'setCreatedByUser',
            'createdOnDate'      => 'setCreatedOnDate',
            'brokerId'           => 'setBrokerId',
            'isEnabled'          => 'setIsEnabled',
            'ssoMapper'          => 'setSsoMapper'
        ];

        return $methods;            
    }

}