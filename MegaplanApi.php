<?php

    require_once('Request.php');
    // require_once('RequestInfo.php');

    /*
     * @author SADESIGN
     * */
    class MegaplanApi {
        //You must enter your own Megaplan data to enable Debug mode. After all tests have been made, EMPTY ALL FIELDS;
        const DEBUG = false;
        const DebugScheme = 'https';
        const DebugLogin = 'kostya-d2002@mail.ru'; // Megaplan Account Loggin
        const DebugPass = 'b48aLLjok'; // Megaplan Account Password
        const DebugHost = 'mega-test.megaplan.ru'; // Megaplan Test Host


        protected static $Category = [
            'common' => 'BumsCommonApiV01',
            'task' => 'BumsTaskApiV01',
            'project' => 'BumsProjectV01',
            'staff' => 'BumsStaffApiV01',
            'todo' => 'BumsTimeApiV01',
            'trade' => 'BumsTradeApiV01',
            'crm' => 'BumsCrmApiV01',
            'invoice' => 'BumsInvoiceApiV01',
            'discuss' => 'BumsDiscussApiV01',
        ];

        protected static $ObjectType = [
            'user' => 'User',
            'userInfo' => 'UserInfo',
            'search' => 'Search',
            'task' => 'Task',
            'project' => 'Project',
            'comment' => 'Comment',
            'contractor' => 'Contractor',
        ];

        protected static $Action = [
            'authorize' => 'authorize',
            'createOneTimeKeyAuth' => 'createOneTimeKeyAuth',
            'id' => 'id',
            'quick' => 'quick',
            'list' => 'list',
            'card' => 'card',
            'create' => 'create',
            'save' => 'save',
        ];

        protected static $actionType = [
            'xml' => 'xml',
            'json' => 'api',
        ];


        private $requestApi;
        private $requestInfoApi;

        public $ch; // curl handler
        public $https = false;
        public $host; // host for API request
        public $login; // Account login
        public $pass; // Account Pass
        public $accessId; // AccessId for request session
        public $secretKey; // secretKey for request session
        public $oneTimeKey; // temporary key instead of login/password access


        public $UserId; // System user id
        public $EmployeeId; // System employee id
        public $Folder = 'all'; //Allowed values: "incoming","responsible","executor","owner","auditor","all"
        public $TimeUpdated ; //Return objects after particular date (date/time format ISO 8601)
        public $Status= 'any'; // Allowed values: "actual", "inprocess", "new", "overdue", "done", "delayed", "completed", "failed", "any"
        public $FavoritesOnly = 0;  // Allowed Values: 0, 1
        public $Search; //Query string
        public $Detailed = false; //Print all fields of a task Allowed values: true, false
        public $OnlyActual = false;// Print onlu current tasks Allowed values: true, false
        public $FilterId; //string  Allowed value: any string
        public $Count = false; //Print amount of task instead of full list Allowed values: true, false
        public $ProjectId; //return list of tasks connected with current projectId
        public $SuperTaskId; // Return child task list of SuperTaskId
        public $SortBy; // Result sorting Allowed value: "id", "name", "activity", "deadline", "responsible", "owner", "contractor", "start","plannedFinish","plannedWork", "actualWork", "completed","bonus","fine"б "plannedTime" - длительность
        public $SortOrder = 'asc'; //   Sort order Allowed values: "asc", "desc"
        public $ShowActions = false; // Show list of available actions Allowed values: true, false
        public $Limit = 50  ; //integer Amount of tasks(LIMIT)  Integer value range[1,100]
        public $Offset; // task OFFSET

        public $SubjectType; // Object type Allowed values: task, project, contractor, deal, discuss
        public $SubjectId;  // commenting object ID
        public $Model = [
            'Text' => '', // Comment text of a Model object
            'Work' => '',// Amount of spent time for current task/project. Part of a Model object
            'WorkDate' => '',// Date to which an amount of time will be spent to. Part of a Model object
            'Attaches' => '', // Array of attached files . Must be sent over POST request. Part of a Model object. Array
                              // Data(file content) encoded with MIME base64. Part of Attaches
                              // file name. They will be displayed with comments
        ];

        public $id;
        public $RequestedFields;
        /**
         * @param null $scheme // http/https
         * @param null $host  // host name of saas megaplan
         * @param null $login
         * @param null $password
         * @param bool $debug //
         */
        public function __construct( $scheme = null, $host = null, $login = null, $password = null,  $debug = self::DEBUG){
            if($debug){
                $this->https = (self::DebugScheme === 'https') ? true : false;
                $this->host = self::DebugHost;
                $this->login = self::DebugLogin;
                $this->pass = self::DebugPass;
            }else{
                $this->https = ($scheme === 'https') ? true : false;
                $this->host = trim(filter_var($host, FILTER_SANITIZE_URL));
                $this->login = trim(filter_var($login, FILTER_SANITIZE_STRING));
                $this->pass = trim(filter_var($password, FILTER_SANITIZE_STRING));
            }

            if(empty($this->host) || empty($this->login) || empty($this->pass))
                throw new InvalidArgumentException('Missing authentication parameter for api');

        }

        /**
         * @return resource
         */
        public function init($url = null){
            $this->ch = curl_init($url);
            return $this->ch;
        }

        /**
         * @param $url
         */
        public function close($url){
                curl_close($url);
            }

        /**
         * @param $toggle
         */
        public function setHttps($toggle){
            $this->https = $toggle;
        }

        /**
         * @return string
         */
        protected function getScheme(){
            return $this->https ? 'https' : 'http';
        }

        public function getPath($category,$objectType,$action,$actionType){
            $path = '/';
            if(array_key_exists($category,self::$Category)){
                $path .= self::$Category[$category] . '/';
            }
            if(array_key_exists($objectType, self::$ObjectType)){
                $path .= self::$ObjectType[$objectType] . '/';
            }
            if(array_key_exists($action, self::$Action)){
                $path .= self::$Action[$action] . '.';
            }
            if(array_key_exists($actionType, self::$actionType)){
                $path .= self::$actionType[$actionType];
            }
            return $path;
        }

        /**
         * @param $url
         * @param $args
         * @return string
         */
        public function formLink($uri,$args){
            $url = $this->getScheme();
            $url .= '://' . $this->host.$uri;
            $url[strlen($url)] = '?';
            foreach($args as $key => $arg){
                $url .= $key . '=' . $arg . '&';
            }
            return substr($url,0,-1);
        }

        /**
         * @return mixed
         */
        protected function request(){
            return curl_exec($this->ch);
        }

        /**
         * @return SdfApi_Request
         */
        protected function getApiRequest(){
            if(empty($this->requestApi)){
                $this->requestApi = new SdfApi_Request($this->accessId,$this->secretKey,$this->host,$this->https);
            }
            return $this->requestApi;
            }

        /**
         * @param $method
         * @param $host
         * @param $Uri
         * @param $headers
         * @return SdfApi_RequestInfo
         * @throws Exception
         */
        protected function getInfoApiRequest($method,$host,$Uri,$headers){
                if(empty($this->requestInfoApi)){
                    $this->requestInfoApi = SdfApi_RequestInfo::create($method,$host,$Uri,$headers);
                }
                return $this->requestInfoApi;
            }

        /**
         * @param $options
         * @param null $value
         * @return bool
         */
        protected function setOpt($options,$value=null){
                return curl_setopt($this->ch,$options,$value);
            }

        /**
         * @param $masterList
         * @param $compareList
         * @return array
         */
        protected function formArg($masterList, $compareList){
                return array_intersect_key($masterList,$compareList);
            }

        /**
         * OneTimeKeyAuth
         * https://help.megaplan.ru/API_onetimekey
         * @return mixed if a request failed,
         * @return OneTimeKey string if a request successed
         * @throws ErrorException
         */
        public function getOneTimeKey(){
            $this->init();
            $arg = [
                'Login' => $this->login,
                'Password' => md5($this->pass),
            ];
            $path = $this->getPath('common','user','createOneTimeKeyAuth','json');
            $link = $this->formLink($path,$arg);
            $this->setOpt(CURLOPT_URL,$link);
            $response =  $this->request();
            if($response === false)
                throw new ErrorException('Invalid URL or login/password: ' . $link);
            $response = json_decode($response,true);
            if(array_key_exists('OneTimeKey',$response)){
                $this->oneTimeKey = $response['OneTimeKey'];
                return $this->oneTimeKey;
            }
            $this->close($this->ch);
            return $response;
            }

        /**
         *
         * @param null $oneTimeKey
         * @return mixed
         */
        public function auth($oneTimeKey = null){
            $this->init();
            if(empty($oneTimeKey)){
                $args = [
                    'Login' => $this->login,
                    'Password' => md5($this->pass),
                ];
            }else{
                $args = [
                    'OneTimeKey' => $oneTimeKey,
                ];
            }
            $path = $this->getPath('common','user','authorize','json');
            $link = $this->formLink($path,$args);
            $this->setOpt(CURLOPT_URL,$link);
            $this->setOpt(CURLOPT_RETURNTRANSFER,1);
            $response = $this->request();
            $response = json_decode($response,true);
            $this->close($this->ch);
            if(array_key_exists('status',$response)){
                if($response['status']['code'] === 'ok'){
                    $this->accessId = $response['data']['AccessId'];
                    $this->secretKey = $response['data']['SecretKey'];
                    $this->UserId = $response['data']['UserId'];
                    $this->EmployeeId = $response['data']['EmployeeId'];
                    return [
                        'accessId' => $this->accessId,
                        'secretKey' => $this->secretKey,
                        'UserId' => $this->UserId,
                        'EmployeeId' => $this->EmployeeId
                    ];
                }
                return $response['status']['code'];
            }
            return $response;
        }

        /**
         *  https://help.megaplan.ru/API_task_list
         */
        public function getTaskList($params){
            $this->init();
            $masterList = get_class_vars(get_class($this));
            $arg = $this->formArg($params,$masterList);
            $path = $this->getPath('task','task','list', 'json');
            $request = $this->getApiRequest();
            $response = $request->get($path,$arg);
            $this->close($this->ch);
            return json_decode($response,true);
        }

        /**
         * https://help.megaplan.ru/API_task_card
         * @param $id
         * @return mixed
         */
        public function getTaskCard($params){
            $path = $this->getPath('task','task','card','json');
            $masterList = get_class_vars($this);
            $arg = $this->formArg($params,$masterList);
            $request = $this->getApiRequest();
            $response = $request->get($this->host.$path, $arg);
            return json_decode($response,true);
        }
        /*
         * https://help.megaplan.ru/API_project_list
         * @param $params
         * @return mixed
         */
        public function getProjectList($params){
            $path = $this->getPath('project','project','list','json');
            $masterList = get_object_vars($this);
            $arg = $this->formArg($params, $masterList);
            $request = $this->getApiRequest();
            $response = $request->get($path,$arg);
            return json_decode($response,true);
        }

        /**
         * https://help.megaplan.ru/API_project_card
         * @param $params
         * @return mixed
         */
        public function getProjectCard($params){
            $attrList = get_object_vars($this);
            $params = $this->formArg($params,$attrList);
            $path = $this->getPath('project','project','card','json');
            $request = $this->getApiRequest();
            $response = $request->get($path,$params);
            return json_decode($response,true);
        }

        /**
         * https://help.megaplan.ru/API_comment_create
         * @param $params
         * @return mixed
         */
        public function addComment($params){
            $attrList = get_object_vars($this);
            $params = $this->formArg($params,$attrList);
            $path = $this->getPath('common','comment','create','json');
            $request = $this->getApiRequest();
            $response = $request->post($path,$params);
            return json_decode($response,true);

        }

        /**
         * https://help.megaplan.ru/API_contractor_list
         * @param $params
         * @return mixed
         */
        public function getClientList($params){
            $path = $this->getPath('crm','contractor','list','json');
            $masterList = get_object_vars($this);
            $arg = $this->formArg($params, $masterList);
            $request = $this->getApiRequest();
            $response = $request->get($path,$arg);
            return json_decode($response,true);
        }

        /**
         * https://help.megaplan.ru/API_contractor_card
         * @param $params
         */
        public function getClientCard($params){
            $attrList = get_object_vars($this);
            $params = $this->formArg($params,$attrList);
            $path = $this->getPath('crm','contractor','card','json');
            $request = $this->getApiRequest();
            $response = $request->get($path,$params);
            return json_decode($response,true);
        }

        public function addClient($params){
            $path = $this->getPath('crm','contractor','save','json');
            $attrList = get_object_vars($this);
            $params = $this->formArg($params,$attrList);
            $request = $this->getApiRequest();
            $response = $request->post($path,$params);
            return json_decode($response,true);
        }

        public function editClient($params){
            // TODO edit client card
        }

        public function removeClient(){
            // TODO Implement remove query
        }

        public function addDeal($params){
            $path = $this->getPath('crm','contractor','save','json');
            $attrList = get_object_vars($this);
            $params = $this->formArg($params,$attrList);
            $request = $this->getApiRequest();
            $response = $request->post($path,$params);
            return json_decode($response,true);
        }


        /**
         * @param $client_id
         * @param array $filters
         * @return bool| array Contractor
         */
        public function isClientExists($client_id, array $filters = []){
            $clientList = $this->getClientList($filters);
            foreach($clientList['data'] as $client){
                switch($client_id){
                    case $client['Id']:
                        return $client;
                    case $client['Email']:
                        return $client;
                    case $client['Name']:
                        return $client;
                }
            }
            return false;
        }
    }

?>