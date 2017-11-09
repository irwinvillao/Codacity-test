<?php defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
    protected $data = array();

    function __construct()
    {
        parent::__construct();
    }

    protected function render($the_view = NULL, $layout = '_layouts/fullscreen_layout')
    {
        if($layout == 'json' || $this->input->is_ajax_request())
        {
            header('Content-Type: application/json');
            echo json_encode($this->data);
        }
        else
        {
            $this->data['the_view_content'] = (is_null($the_view)) ? '' : $this->load->view($the_view, $this->data, TRUE);
            $this->load->view($layout, $this->data);
        }
    }
}

class Apps_Controller extends MY_Controller
{
    protected $emPortal;
    protected $emLearn;
    protected $emThrone;
    protected $emComply;

    function __construct()
    {
        parent::__construct();
        $this->emPortal      = $this->doctrine->em_portal;
        $this->emLearn       = $this->doctrine->em_learn;
        $this->emThrone      = $this->doctrine->em_throne;
        $this->emComply      = $this->doctrine->em_comply;
    }

    protected function render($the_view = NULL, $layout = '_layouts/fullscreen_layout')
    {
        parent::render($the_view, $layout);
    }

    protected function render_single($the_view = NULL)
    {
        $this->load->view($the_view, $this->data);
    }

    protected function getGuzzleClient()
    {
        return new GuzzleHttp\Client(['base_uri' => $this->config->item('api_root')]);
    }

    protected function apiGetRequest($uri = '')
    {
        $client         = $this->getGuzzleClient();
        $responseRaw    = $client->get($uri, ['verify' => false]);
        $responseObject = json_decode($responseRaw->getBody()->getContents());
        return $responseObject;
    }

    protected function apiPostRequest($uri = '', $params = [])
    {
        $client         = $this->getGuzzleClient();
        $responseRaw    = $client->post($uri, ['verify' => false, 'form_params' => $params]);
        $responseObject = json_decode($responseRaw->getBody()->getContents());
        return $responseObject;
    }

    protected function apiPutRequest($uri = '', $params = [])
    {
        $client         = $this->getGuzzleClient();
        $responseRaw    = $client->put($uri, ['verify' => false, 'form_params' => $params]);
        $responseObject = json_decode($responseRaw->getBody()->getContents());
        return $responseObject;
    }
}

require APPPATH . '/libraries/REST_Controller.php';

class REST_Controller_Proxy extends REST_Controller
{

    protected $user_client_id;

    function __construct()
    {
        parent::__construct();
        $this->emPortal    = $this->doctrine->em_portal;
        $this->emLearn     = $this->doctrine->em_learn;
        $this->emComply    = $this->doctrine->em_comply;

        // REQUEST DEFAULTS
        $this->has_request_page   = (IS_NULL($this->get('page')))   ? FALSE : TRUE;
        $this->has_request_filter = (IS_NULL($this->get('filter'))) ? FALSE : TRUE;

        // CUSTOM MESSAGES
        $this->lang->load('messages', $language);
    }

    public function __destruct()
    {
        // Get the current timestamp
        $this->_end_rtime = microtime(TRUE);

        // Log the loading time to the log table
        if ($this->config->item('rest_enable_logging') === TRUE)
        {
            $this->_log_access_time();
        }
    }

    function validateRequiredFields($apiCallFields, $requiredFields, $message = null)
    {
        foreach ($requiredFields as $key => $value) {
            if (!array_key_exists($value, $apiCallFields)) {
                $message = ($message) ?: 'Missing required field: ' . $value;
                $this->proxy_response(array($value => 'required'), '0', $message, 'ERROR');
            }
        }
    }

    function getEntityFields($className)
    {
        $entityFieldsArray = array();
        $metadata = $this->emLearn->getClassMetadata($className);
        return $metadata->fieldNames;
    }

    /**
     * Format data before sending an API response.
     * 
     * @param  array    $data       Expects associative array with keys 'messages' and 'results'. Example: $data = ['messages'=>$messagesArr, 'results'=>$resultsArr]
     * @param  integer  $httpCode   An HTTP status code found in self::$http_status_codes
     * @param  boolean  $continue   See same variable of parent class method.
     * @return 
     */
    public function response($data=null, $httpCode=null, $continue=false) 
    {
        // Keys 'results' and 'messages' are reserved, contents to be included in the formatted response
        $data = array_merge([
            'results'  => [],
            'messages' => []
        ], $data);

        // Unified API response format
        $out = [
            'status' => in_array($httpCode, [REST_Controller::HTTP_OK, REST_Controller::HTTP_CREATED]),
            'sys' => [
                'status'   => $httpCode,
                'messages' => $data['messages']
            ],
            'results' => $data['results']
        ];

        // Additional content in $data is included in 'sys.extra'
        unset($data['messages']);
        unset($data['results']);
        if (!empty($data)) {
            $out['sys']['extra'] = $data;
        }

        parent::response($out, $httpCode, $continue);
    }

    /**
     * Deprecated
     * Instead, please use: $this->response()
     */
    protected function proxy_response($results, $total_count = NULL, $message = '', $http_response = 'GET')
    {
        if($http_response != 'ERROR' && ($results || (!count($results) && !is_null($total_count)))) {
            $single_resource = true;
            $message         = 'A single resource was found.';

            if($total_count == 0) {
                $message = 'No results on the requested resource were found.';
            }
            if(is_null($total_count)) {
                $total_count = 1;
            }
            if(is_array($results) && (array_key_exists(0, $results))) {
                $single_resource = false;
                $message         = 'A total of ' . count($results) . ' resources were found.';
                $total_count     = count($results);
            }
            if(is_array($results) && isset($results['totalRecords'])) {
                $single_resource = false;
                $message         = "A total of ${results['totalRecords']} resources were found.";
                $total_count     = $results['totalRecords'];
            }

            switch ($http_response) {
                case 'POST':
                    $this->response([
                        'messages' => $message ? [$message] : [],
                        'results' => $results
                    ], REST_Controller::HTTP_CREATED);
                    break;

                case 'PUT':
                case 'DELETE':
                default:
                    $this->response([
                        'messages' => $message ? [$message] : [],
                        'results' => $results
                    ], REST_Controller::HTTP_OK);
                    break;
            }
        } else {

            if ($http_response == 'ERROR'){
                $this->response([
                    'messages' => $message ? [$message] : [],
                    'results' => $results
                ], REST_Controller::HTTP_BAD_REQUEST);
            } else {
                $this->response([
                    'messages' => $message ? [$message] : [],
                    'results' => $results
                ], REST_Controller::HTTP_NOT_FOUND);
            }

        }

    }

    protected function getGuzzleClient()
    {
        return new GuzzleHttp\Client(['base_uri' => $this->config->item('api_root')]);
    }

    protected function apiGetRequest($uri = '')
    {
        $client         = $this->getGuzzleClient();
        $responseRaw    = $client->get($uri, ['verify' => false]);
        $responseObject = json_decode($responseRaw->getBody()->getContents());
        return $responseObject;
    }

    protected function apiPostRequest($uri = '', $params = [])
    {
        $client         = $this->getGuzzleClient();
        $responseRaw    = $client->post($uri, ['verify' => false, 'form_params' => $params]);
        $responseObject = json_decode($responseRaw->getBody()->getContents());
        return $responseObject;
    }

    protected function apiGetRequest_SendGrid($uri = '', $params = [])
    {
        $client         = new GuzzleHttp\Client(['base_uri' => 'https://api.sendgrid.com']);
        $responseRaw    = $client->get($uri, [
            'verify' => false,
            'headers' => [
                'Content-Type'     => 'application/json',
                'Authorization'      => 'Bearer ' . $this->config->item('sendgrid_api_key')
            ]
        ]);

        $responseObject = json_decode($responseRaw->getBody()->getContents());
        return $responseObject;
    }

    protected function apiPostRequest_SendGrid($uri = '', $params = [])
    {
        $client         = new GuzzleHttp\Client(['base_uri' => 'https://api.sendgrid.com']);
        $responseRaw    = $client->post($uri, [
            'verify' => false,
            'headers' => [
                'Content-Type'     => 'application/json',
                'Authorization'      => 'Bearer ' . $this->config->item('sendgrid_api_key')
            ],
            'json' => $params
        ]);

        $responseObject = json_decode($responseRaw->getBody()->getContents());
        return $responseObject;
    }

    protected function apiGetRequest_ContentfulDeliveryAPI($space_api_access_token, $uri, $params = [])
    {
        $client         = new GuzzleHttp\Client(['base_uri' => 'https://cdn.contentful.com']);
        $responseRaw    = $client->get($uri, [
            'verify' => false,
            'headers' => [
                'Content-Type'     => 'application/json',
                'Authorization'      => 'Bearer ' . $space_api_access_token
            ]
        ]);

        $responseObject = json_decode($responseRaw->getBody()->getContents());
        return $responseObject;
    }

    protected function _parse_delete()
    {
        if ($this->request->format)
        {
            $this->request->body = $this->input->raw_input_stream;
            if ($this->request->format === 'json')
            {
                $this->_delete_args = json_decode($this->input->raw_input_stream);
            }
        }
        // These should exist if a DELETE request
        if ($this->input->method() === 'delete')
        {
            $this->_delete_args = $this->input->input_stream();
        }
    }

	protected function sendEmail($params){
        extract($params);
        $array_counter  = "";
        $emails_sent    = 0;
        $error_sendgrid = "The email has not been sent. There was a problem with Sendgrid.";

        if(
            (is_array($template)) &&
            (is_array($array_contacts))
        ){
            $array_emails       = array_count_values(array_column($array_contacts, "email"));
            $array_counter      = "";
            $body               = $template["template"]["body"];
            $subject            = $template["template"]["subject"];
            $from_name          = (isset($template["template"]["from_name"])) ? $template["template"]["from_name"] : "ThinkHR";
            $from_email         = (isset($template["template"]["from_email"])) ? $template["template"]["from_email"] : "welcome@myhrworkplace.com";

            foreach ($array_emails as $key => $value) {
                if($value > 1){
                    $array_counter[$key] = 0;
                }
            }

            foreach ($array_contacts as $key => $value) {
                if($value["active"] == 1){
                    $number_email        = "";
                    $reset_password_link = $api_root . "/reset-password/" . $array_passwords[$value["id"]];
                    if(
                        (is_array($array_counter)) && 
                        (array_key_exists($value["email"], $array_counter))
                    ){
                        $array_counter[$value["email"]] = $array_counter[$value["email"]] + 1;
                        $number_email                   = " - (" . $value["username"] . ")";
                    }
                    $personalizations[$emails_sent]["to"][0]["email"]                    = $value["email"];
                    $personalizations[$emails_sent]["to"][0]["name"]                     = $value["first_name"];
                    $personalizations[$emails_sent]["substitutions"]["%FIRSTNAME%"]      = $value["first_name"];
                    $personalizations[$emails_sent]["substitutions"]["%USERNAME%"]       = $value["username"];
                    $personalizations[$emails_sent]["substitutions"]["%SET_PW_LINK%"]    = $reset_password_link;
                    $personalizations[$emails_sent]["substitutions"]["%SET_LOGIN_LINK%"] = $api_root;
                    $personalizations[$emails_sent]["substitutions"]["%logo%"]           = $template["template"]["header_logo"];
                    $personalizations[$emails_sent]["subject"]                           = $subject . $number_email;
                    $personalizations[$emails_sent]["substitutions"]["-header_color-"]   = $template["template"]["header_color"];
                    $personalizations[$emails_sent]["substitutions"]["-border_color-"]   = $template["template"]["border_color"];
                    $emails_sent++;
                }
            }

            $result_sendgrid = "";
            if(is_array($personalizations)){
                try{
                    $params = [
                        'personalizations' => $personalizations,
                        'from' => [
                            'email' => $from_email,
                            'name'  => $from_name,

                        ],
                        'template_id' => $template["sendgrid_id"],
                        'content' => [
                            [
                                'type'  => 'text/html',
                                'value' => $body
                            ],
                        ]
                    ];
                    $this_time       = date("Y-m-d H:i:s");
                    $time_sent       = strtotime($this_time);
                    $result_sendgrid = $this->apiPostRequest_SendGrid('/v3/mail/send', $params);
                    $result["error"] = 0;

                } catch(Exception $er){
                    $exception_error = "";
                    $sendgrid_error  = $er->getMessage();
                    $result["error"] = 5;
                    preg_match('~{"message":.*?","~m', $sendgrid_error, $matches);
                    if(
                        (is_array($matches)) &&
                        (isset($matches[0]))
                    ){
                        $exception_error         = $matches[0];
                        $exception_error         = str_replace('{"message":"', '', $exception_error);
                        $exception_error         = str_replace('","', '', $exception_error);
                        $result["error_message"] = "Sendgrid error: " . $exception_error;
                    }else{
                        $result["error_message"] = $error_sendgrid;
                    }
                }

            }else{
                $result["error"]         = 6;
                $result["error_message"] = $error_sendgrid;
            }
        }
        $result["emails_sent"] = $emails_sent;

        return $result;    
    }

    protected function makeProxyRequestPortal(array $args, $debug = false)
    {
        $portal_proxy_configs = [
            'local' => [
                'root_domain'   => 'rockstars.thinkhr-local.com'
            ],
            'development' => [
                'root_domain'   => 'rockstars.thinkhr-dev.com'
            ],
            'testing' => [
                'root_domain'   => 'rockstars.thinkhr-qa.com'
            ],
            'production' => [
                'root_domain'   => 'rockstars.thinkhr.com'
            ],
        ];

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

        // build url and get access token
        {
            $env            = THR::getEnvironment();
            $url            = 'https://' . $portal_proxy_configs[$env]['root_domain'] . '/' . ltrim($path, '/');

            if ($query) {
                $url .= '&' . $query;
            }
        }

        // build request
        {
            $args = (THR::getEnvironment() == 'local') ? "-k -o - -s -w '\n%{http_code}\n'" : "-o - -s -w '\n%{http_code}\n'";
            $data = $body ? "--data '$body'" : "";

            $request = "
                curl $args \
                    --request $method \
                    --url '$url' \
                    --header 'accept: application/json' \
                    --header 'content-type: application/json' \
                    $data
            ";
        }

        // make request
        {
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

            $status = ($status >= 200 && $status < 300) ? 'success' : 'error';

            return [json_decode($body, true), $status];
        }
    }

}

