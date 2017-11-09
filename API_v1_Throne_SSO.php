<?php
/**
 * Throne SSO
 * Add description here...
 *
  * @@@@@@
 */
defined('BASEPATH') OR exit('No direct script access allowed');

require_once realpath(__DIR__ . '/../../../../THR.php');

class API_v1_Throne_SSO extends REST_Controller_Proxy
{
    private $authenticationBrokerRepository;
    private $companyRepository;
    private $companyMapperRepository;
    private $ssoEncryptionKey;
    private $urlWorkplaceSession;

    public function __construct() {
        parent::__construct();
        $this->authenticationBrokerRepository = $this->emPortal->getRepository('Throne\Entity\AuthenticationBroker');
        $this->companyRepository              = $this->emPortal->getRepository('Throne\Entity\Company');
        $this->companyMapperRepository        = $this->emPortal->getRepository('Throne\Entity\CompanyMapper');
        $this->logSsoRepository               = $this->emPortal->getRepository('Throne\Entity\LogSso');
        $this->ssoEncryptionKey               = $this->config->item('sso_encryption_key');
        $this->urlWorkplaceSession            = $this->config->item('url_workplace_session');
    }

    /**
     * Add description
     * ...
     *
     * @param string $AuthCode
     * @param string $SSOID or email
     * @param string $OriginEncrypted
     * @return array 
     */
    public function sso_get()
    {   
        //Obs_: usar self
        $result                  = $this->validateSsoParameters(); // verifies authentication code and referer / origin
        $result["error_message"] = "";
        $message                 = "";
        
        // verifies if user belongs to throne or workplace
        {
            if ($result["success"]) {
                
                $throneUser = $this->emPortal->getRepository('Throne\Entity\User')->readThroneUserByMapper($result);

                if ($throneUser) { // Set session if is a Throne user
                    $setThroneSession = self::setThroneSession($throneUser['id'], $result);
                    $result           = array_merge($result, $setThroneSession);
                
                } else {  // Set token to access Workplace
                    $workplaceUser = $this->emPortal->getRepository('Throne\Entity\User')->readContactUserByMapper($result);
                    
                    if ($workplaceUser) {
                        $setWorkplaceSession = self::setWorkplaceSession($workplaceUser["id"], $result);
                        $result              = array_merge($setWorkplaceSession, $result);
                    
                    } else {
                        $result["error_message"] = $this->lang->line('invalid_email');
                        $result["success"]       = false;
                        $result["error"]         = 'invalid_email';
                    }
                }
            } else {
                $result["error_message"] = $this->lang->line($result["error"]);
                $result["success"]       = false;
            }
        }
        
        // logs all the requests via sso (successes or failures to quickly identify any mismatch configuration on the client side)
        $this->saveSsoLog($result);
        
        if(!$result["host"]) {
            $redirect = 'https://apps.' . \THR::getRootDomain() . '/login';
            redirect($redirect);
        }
        unset($result["data"]);

        self::returnResponse($result);

    }

   /**
     * Add description
     * ...
     *
     * @param integer $id
     * @return array
     */
    public function sso_broker_configuration_get()
    {
        $brokerId = (int)$this->get('id');
        $result   = ($brokerId > 0) ? $this->authenticationBrokerRepository->read($brokerId) : "";
        $result["error_message"] = (!$result) ? $this->lang->line("no_results_found") : "";
        self::returnResponse($result);
    }


    /**
     * Add description
     * ...
     *
     * @param sring $authenticationCode
     * @param string $url
     * @param string $brokerId
     * @param string $ssoMapper
     * @return array
     */
    public function sso_broker_configuration_post()
    {
        $result = self::processAction($this->post(), 'create', 'authenticationBrokerRepository');
        self::returnResponse($result);
    }

    /**
     * Add description
     * ...
     *
     * @param sring $authenticationCode
     * @param string $url
     * @param string $brokerId
     * @param string $ssoMapper
     * @return array
     */
    public function sso_broker_configuration_put()
    {
        $result = self::processAction($this->put(), 'update', 'authenticationBrokerRepository');
        self::returnResponse($result);
    }


   /**
     * Add description
     * ...
     *
     * @param integer $id
     * @return array
     */
    public function sso_company_mapper_get()
    {
        $companyId = (int)$this->get('id');
        $result    = ($companyId > 0) ? $this->companyMapperRepository->read($companyId) : "";
        $result["error_message"] = (!$result) ? $this->lang->line("no_results_found") : "";
        self::returnResponse($result);
    }  

    /**
     * Add description
     * ...
     *
     * @param sring $companyId
     * @param string $ssoCompanyName
     * @param string $ssoMapper
     * @return array
     */
    public function sso_company_mapper_post()
    {
        $result = self::processAction($this->post(), 'create', 'companyMapperRepository');
        self::returnResponse($result);
    }

    /**
     * Add description
     * ...
     *
     * @param sring $companyId
     * @param string $ssoCompanyName
     * @param string $ssoMapper
     * @return array
     */
    public function sso_company_mapper_put()
    {
        $result = self::processAction($this->put(), 'update', 'companyMapperRepository');
        self::returnResponse($result);
    }

    /**
     * Add description
     * ...
     * @param array $result
     * @return json
     */
    private function returnResponse($result)
    {
        $message = @$result["error_message"];
        $self    = (!$message) ? self::HTTP_OK : self::HTTP_BAD_REQUEST;

        unset($result["error_message"]);
        $this->response([
            'results'  => $result,
            'messages' => $message
        ], $self);
    }

    /**
     * Add description
     * ...
     *
     * @param array $request (POST, PUT)
     * @param string $action (create, update) 
     * @param string $repository
     * @return array
     */
    private function processAction(array $request, $action, $repository)
    {
        $defaultFields     = self::getFieldsByRepository($repository);
        $requiredFields    = $defaultFields["required"];
        $this->validateRequiredFields($request, $requiredFields);
        $emptyFields       = self::validateEmptyFields($requiredFields, $request);

        if (!$emptyFields) {
            $data = $request;
            if ($action == "create") {
                $initializeFields = $defaultFields["initialize"];
                $data             = array_merge($initializeFields, $data);
            }

            $result = $this->$repository->$action($data);
            if ($result["error"]) {
                $result["error_message"] = $this->lang->line($result["error"]);    
            }
        } else{
            $result["error_message"] = $this->lang->line("empty_fields") . ": " . implode(",", $emptyFields);
        }
        return $result;
    }

    /**
     * Add description
     * ...
     *
     * @param string $entity
     * @return array
     */
    private function getFieldsByRepository($entity)
    {
        $now    = date("Y-m-d H:i:s");
        $result = "";
        switch ($entity) {
            case 'authenticationBrokerRepository':
                $result["initialize"]["isEnabled"]     = "1";
                $result["initialize"]["createdByUser"] = \THR::getThrContactId();
                $result["initialize"]["createdOnDate"] = $now;
                $result["required"]                    = array('authenticationCode', 'url', 'brokerId');
                break;

            case 'companyMapperRepository':
                $result["initialize"]["updatedBy"] = \THR::getThrContactId();
                $result["initialize"]["created"]   = $now;
                $result["initialize"]["updated"]   = $now;
                $result["initialize"]["status"]    = "1";
                $result["required"]                = array('companyId', 'ssoCompanyName');
                break;
        }

        return $result; 
    }

    /**
     * Add description
     * ...
     *
     * @param int $userId
     * @param array $dataUser
     * @return array
     */
    private function setThroneSession($userId, $dataUser = NULL)
    {
        $result["error_message"] = "";
        $result["success"]       = false;
        $throneUserObject        = $this->emPortal->getRepository('Throne\Entity\User')->findOneBy(['id' => $userId]);
        if ($throneUserObject) {
            if ($throneUserObject->getActive() != 1) {
                $result["error_message"][] = $this->lang->line('suspended_account');
            }
            if (!$this->companyRepository->isRequestIpInCompanyWhitelist($throneUserObject->getCompany())) {
                $result["error_message"][] = $this->lang->line('ip_not_in_whitelist');
            }
            if (!$result["error_message"]) {
                $app                   = ($app_referer == \THR::APP_PULSE) ? \THR::APP_PULSE : \THR::APP_THRONE;
                $portal_user           = "";
                $learn_user            = "";
                $result["environment"] = "throne";
                $result["userId"]      = $throneUserObject->getId();
                $result["username"]    = $throneUserObject->getUsername();
                $result["companyId"]   = (!isset($result["companyId"])) ? $throneUserObject->getCompany()->getId() : $result["companyId"];

                //Set Portal and Learn User
                {
                    if ($throneUserObject->getCompany()->getId() == 8148) {
                        $portal_user    = $this->emPortal->getRepository('Portal\Entity\User')->findOneBy(['username' => $result["username"]]);
                        $learn_user     = $this->emLearn->getRepository('Learn\Entity\User')->findOneBy(['username' => $result["username"], 'company_id' => 10571]);
                        $result["isThrEmployee"] = "1";
                    } else { // is not thr employee, client is_active and has a configuration_id
                        $learn_company = $this->emLearn->getRepository('Learn\Entity\Company')->findOneBy(['thr_client_id' => $throneUserObject->getCompany()->getId()]);

                        if ($learn_company) {
                            $learn_user = $this->emLearn->getRepository('Learn\Entity\User')->findOneBy(['username' => $result["username"], 'company_id' => $learn_company->getId()]);
                        }
                        $result["isThrEmployee"] = "0";
                    }
                }
                // Combine arrays to assemble the JSON
                $result            = array_merge($dataUser, $result);
                $result["success"] = true;

                // if the host variable does not exist it means that the call to the service was to throne 
                if(!$result["host"]) {
                    // set authenticated user session data
                    $this->emPortal->getRepository('Throne\Entity\User')->setAuthenticatedUserSessionData(@$username, @$throneUserObject, @$portal_user, @$learn_user, $app);

                    $this->saveLoginThrone(@$throneUserObject, @$learn_user);
                    if(!isset($result["email"])) $result["email"] = $throneUserObject->getEmail();
                    $this->saveSsoLog($result);  
                    $login_redirect_url = $_SESSION['THR_AUTH_USER']['login_redirect_url'];
                    $redirect           = (strpos($login_redirect_url, 'apps') === false) ? $login_redirect_url : 'https://apps.' . \THR::getRootDomain() . '/en-us';
                    redirect($redirect, 'refresh');
                    
                } else { // if the call to the service was from thinkhr I return the json with the hashcode
                    $emailUsername      = ($dataUser["ssoMapper"] == "email" && isset($dataUser["email"])) ? $dataUser["email"] : $result["username"];
                    $result["hashCode"] = self::createHash(uniqid() . "|" . $emailUsername . "|" . $result["brokerId"] . "|" . $result["userId"] . "|" . $result["companyId"] . "" );
                }
                
            }
        } else {

            $result["error_message"] = $this->lang->line('invalid_email');
            $result["success"]       = false;
        }    
        
        return $result;
    }

    /**
     * Add description
     * ...
     *
     * @param object $throneUserObject
     * @param object $learnUser
     */
    private function saveLoginThrone($throneUserObject = NULL, $learnUser = NULL)
    {
        // track event
        $this->events->track([
            'app_name'         => 'app_login',
            'action_name'      => 'login',
            'object_type_name' => 'contact_user',
            'object_id'        => ($throneUserObject) ? $throneUserObject->getId() : @$learnUser->getId(),
            'assoc_type_name'  => ($throneUserObject) ? 'app_throne_sso' : 'app_learn_sso',
            'assoc_id'         => null
        ]);
    }

    /**
     * Add description
     * ...
     *
     * @param integer $userId
     * @param array $result
     * @return array
     */
    private function setWorkplaceSession($userId, $result)
    {
        $workplaceUserObject = $this->emPortal->getRepository('Throne\Entity\Contact')->findOneBy(['id' => $userId]);
        if ($workplaceUserObject) {
            if ($workplaceUserObject->getActive()) {
                $result["environment"] = "workplace";
                $result["userId"]      = $workplaceUserObject->getId();
                $result["username"]    = $workplaceUserObject->getUsername();
                $result["username"]    = ($result["username"] == "") ? $result["email"] : $result["username"];
                $result["companyId"]   = (!isset($result["companyId"])) ? $workplaceUserObject->getClientId() : $result["companyId"];
                $result["hashCode"]    = self::createHash(uniqid() . "|" . $result["username"] . "|" . $result["brokerId"] . "|" . $result["userId"] . "|" . $result["companyId"] . "" );
                if (isset($result["host"])) {
                    if (!self::isValidHost($result["host"])) {
                        unset($result["userId"]);
                        unset($result["companyId"]);
                        unset($result["hashCode"]);
                        unset($result["username"]);
                        $result["success"]       = false;
                        $result["error_message"] = $this->lang->line('no_valid_host');
                    }
                } else {
                    $this->saveSsoLog($result);
                    redirect($this->config->item('workplace_root') . $this->urlWorkplaceSession . "?SSOToken=" . $result["hashCode"]);
                }
            } else {
                $result["error_message"] = $this->lang->line('suspended_account');
                $result["success"]       = false;
            }
        } else {
            $result["error_message"] = $this->lang->line('invalid_email');
            $result["success"]       = false;
        }

        return $result;
    }

    /**
     * Add description
     * ...
     *
     * @param string $SSOToken
     * @return array
     */
    private function validateSsoParameters()
    {
        $SSOToken = $this->get('SSOToken');
        if ($SSOToken) {
            self::verifySSOToken($SSOToken);
            exit;
        }
        $originDomain               = parse_url($_SERVER['HTTP_ORIGIN']);
        $refererDomain              = parse_url($_SERVER['HTTP_REFERER']);
        $data["originDomain"]       = ($originDomain["host"]) ? $originDomain["host"] : "";
        $data["refererDomain"]      = ($refererDomain["host"]) ? $refererDomain["host"] : "";
        $data["pregOrigin"]         = ($data["originDomain"]) ? $this->getDomain($data["originDomain"]) : "";
        $data["pregReferer"]        = ($data["refererDomain"]) ? $this->getDomain($data["refererDomain"]) : "";
        $data["authenticationCode"] = $this->get('AuthCode');
        $data["email"]              = $this->get('SSOID');
        $data["email"]              = (!$data["email"]) ? $this->get('Email') : $data["email"];
        $data["email"]              = (strpos($data["email"], " ") !== false) ? str_replace(" ", "+", $data["email"]) : $data["email"];
        $data["originEncrypted"]    = $this->get('OriginEncrypted');
        $data["host"]               = $this->get('Host');
        $result                     = $this->authenticationBrokerRepository->validateAuthenticationCodeGetAccount($data, $this->ssoEncryptionKey);
        $result["redirect"]         = $this->get('Redirect');
        $result["host"]             = $data["host"];
        $result["data"]             = $data;

        return $result;
    }

    /**
     * Add description
     * ...
     *
     * @param string $SSOToken
     * @return array
     */
    public function verifySSOToken($SSOToken)
    {
        $result = $this->authenticationBrokerRepository->verifySSOToken($SSOToken, $this->ssoEncryptionKey);
        
        if (!$result["error"]) {
            $setThroneSession = self::setThroneSession($result["userId"], $result);
            $result           = array_merge($setThroneSession, $result);
        } else {
            $result["error_message"] = $this->lang->line($result["error"]);
        }
        $redirect = 'https://apps.' . \THR::getRootDomain() . '/login';
        redirect($redirect);

    }

    /**
     * Add description
     * ...
     *
     * @param string $url
     * @return string
     */
    private function getDomain($url)
    {
        $result = false;
        $domain = ($url) ? $url : '';
        if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
            $result = $regs['domain'];
        }

        return $result;
    }

    /**
     * Add description
     * ...
     *
     * @param string $value
     * @return string
     */
    private function createHash($value)
    {
        $key       = base64_decode($this->ssoEncryptionKey);
        $now       = gmmktime(gmdate("H"), gmdate("i"), gmdate("s"), gmdate("m"), gmdate("d"), gmdate("Y"));
        $value     = $value . "|" . $now;
        $encrypted = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key), $value, MCRYPT_MODE_CBC, md5(md5($key))));
        if (strpos($encrypted, "+") !== false) {
            $encrypted = str_replace("+", "@", $encrypted);
        }

        return $encrypted;
    }
    
    /**
     * Add description
     * ...
     *
     * @param array $data
     */
    private function saveSsoLog($data)
    {
        $domain         = @$data["log"]["domain"];
        $referer        = "Referer: " . @$data["log"]["referer"] . "\nReferer domain: " . $data["log"]["pregReferer"];
        $serverCode     = "";
        $logQueryString = $data["data"];
        $message        = (is_array($data["error_message"])) ? implode(",", $data["error_message"]) : $data["error_message"];
        foreach ($_SERVER as $key => $value) {
            $serverCode .= $key ." = ". $value ."\n";
        }
        $parameter = "";
        foreach ($_GET as $key => $value) {
            $parameter .= $key ." = ". $value ."\n";
        }
        $host                    = ($data["host"]) ? $data["host"] : "";
        $environment             = (($data["environment"]) && ($data["environment"] == "workplace")) ? "workplace" : "throne";
        $params["server"]        = $serverCode;
        $params["httpUserAgent"] = ($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : "Curl from: " . $host;
        $params["remoteAddr"]    = ($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "";
        $params["httpReferer"]   = $referer;
        $params["url"]           = ($data["log"]["originEncrypted"] != "") ? $data["log"]["originEncrypted"] : $parameter;
        $params["date"]          = date("Y-m-d H:i:s");
        $params["brokerId"]      = ($data["brokerId"]) ? $data["brokerId"] : 0;
        $params["email"]         = ($logQueryString["email"]) ? $logQueryString["email"] : @$data["email"];
        $params["auth"]          = $this->get('AuthCode');
        $params["auth"]          = ((!$params["auth"]) && ($logQueryString["authenticationCode"])) ? $logQueryString["authenticationCode"] : $params["auth"];
        $params["auth"]          = (!$params["auth"]) ? "" : $params["auth"];
        $params["domain"]        = (!$domain) ? "" : $domain;
        $params["parameter"]     = "";
        $params["status"]        = ($data["success"] && $data["success"] == 1) ? 'success' : "error: " . $message;
        $params["type"]          = (($logQueryString["originEncrypted"]) && ($logQueryString["originEncrypted"] != "")) ? 'get_code_' . $environment . '_sso' : 'login_' . $environment . '_sso';
        $params["type"]          = (($data["clientId"]) && ($data["user"]["clientId"])) ? 'login_throne_sso_code' : $params["type"];

        $log                     = $this->logSsoRepository->addLogSso($params);
    }

    /**
     * Add description
     * ...
     *
     * @param string $host
     * @return boolean
     */
    private function isValidHost($host)
    {
        $isValid = false;
        $valid_hosts = array($this->config->item('workplace_root'));
        foreach ($valid_hosts as $value) {
            if (strpos($value, $host) !== false) {
                $isValid = true;
                break;
            }
        }

        return $isValid;
    }

    /**
     * Add description
     * ...
     *
     * @param array $requiredFields
     * @param array $request (POST, PUT)
     * @return array
     */
    private function validateEmptyFields($requiredFields, $request)
    {
        $emptyFields = [];
        foreach ($requiredFields as $key => $value) {
            if (empty($request[$value])) {
                $emptyFields[] = $value;
            }
        }
        return $emptyFields;
    }

}