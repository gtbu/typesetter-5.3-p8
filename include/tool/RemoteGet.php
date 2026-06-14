<?php

namespace gp\tool {

    defined('is_running') or die('Not an entry point...');

    class RemoteGet {

        public static $redirected;
        public static $maxlength = -1;
        public static $debug;
        public static $methods = array('stream','curl','fopen','fsockopen');

        protected $url_array = array();
        protected $body = '';
        protected $headers = '';
        protected $bytes_written_total = 0;

        public static function Test(){
            foreach(static::$methods as $method){
                if( static::Supported($method) ){
                    return $method;
                }
            }
            return false;
        }

        public static function Supported($method){
            if( \gp\tool::IniGet('safe_mode') ){
                return false;
            }

            switch($method){
                case 'fsockopen':
                    return function_exists('fsockopen');

                case 'curl':
                    return function_exists('curl_init') && function_exists('curl_exec');
            }

            return \gp\tool::IniGet('allow_url_fopen');
        }

        public static function Get_Successful($url,$args=array()){
            $getter = new \gp\tool\RemoteGet();
            $result = $getter->Get($url,$args);

            if( is_array($result) ){
                if( (int)$result['response']['code'] >= 200 && (int)$result['response']['code'] < 300 ){
                    return $result['body'];
                }
            }

            return false;
        }

        public function Get($url,$args=array()){
            static::$debug                 = array();
            static::$debug['Redir']        = 0;
            static::$debug['FailedMethods'] = '';
            static::$debug['NotSupported']  = '';
            static::$redirected            = null;

            return $this->_get($url,$args);
        }

        protected function _get($url, $args = array()){

            $this->body = '';
            $this->headers = '';
            $this->bytes_written_total = 0;

            $url = str_replace(' ','%20',$url);
            $url = static::FixScheme($url);
            $this->url_array = static::ParseUrl($url);

            if( $this->url_array === false ){
                return false;
            }

            if( isset($this->url_array['host']) && strtolower($this->url_array['host']) === 'localhost' ){
                $this->url_array['host'] = '127.0.0.1';
                $url = static::unparse_url($this->url_array);
            }

            $defaults = array(
                'method'         => 'GET',
                'timeout'         => 5,
                'redirection'     => 5,
                'httpversion'     => '1.0',
                'user-agent'      => 'Mozilla/5.0 (Typesetter RemoteGet)',
                'ignore_errors'   => false,
                'headers'         => array(),
            );

            $args = array_merge($defaults, is_array($args) ? $args : array());

            foreach(static::$methods as $method){

                if( !static::Supported($method) ){
                    static::$debug['NotSupported'] .= $method.',';
                    continue;
                }

                $result = $this->GetMethod($method,$url,$args);

                if( $result === false ){
                    static::$debug['FailedMethods'] .= $method.',';
                    return false;
                }

                static::$debug['Method'] = $method;
                static::$debug['Len'] = strlen($result['body']);

                return $result;
            }

            return false;
        }

        public function GetMethod($method,$url,$args=array()){
            $func = $method.'_request';
            if( method_exists($this,$func) ){
                return $this->$func($url, $args);
            }
            return false;
        }

       public function stream_request($url, $r)
{
    $arrContext = $this->stream_context($url, $r);
    $context = stream_context_create($arrContext);
    $handle = fopen($url, 'r', false, $context);

    if ($handle === false) {
        static::$debug['stream'] = 'no handle: ' . $url;
        return false;
    }

    static::stream_timeout($handle, $r['timeout']);

    // Fehlerbehandlung für stream_get_contents
    set_error_handler(function ($severity, $message, $file, $line) {
        // Fehler in static::$debug speichern, aber nicht anzeigen
        static::$debug['stream_error'] = $message;
    });

    $strResponse = stream_get_contents($handle, static::$maxlength);

    restore_error_handler();

    if ($strResponse === false || $strResponse === '') {
        static::$debug['stream'] = empty(static::$debug['stream_error'])
            ? 'no response'
            : 'no response: ' . static::$debug['stream_error'];
        fclose($handle);
        return false;
    }

    $theHeaders = static::StreamHeaders($handle);
    fclose($handle);

    $processedHeaders = static::processHeaders($theHeaders);
    $this->body = static::chunkTransferDecode($strResponse, $processedHeaders);

    return $this->ReturnRequest($url, $r, $processedHeaders);
}

        public function stream_context($url,$r){
            $arrContext = array();
            $arrContext['http'] = array(
                'method'           => $r['method'],
                'user_agent'       => $r['user-agent'],
                'max_redirects'    => 0,
                'protocol_version' => (float)$r['httpversion'],
                'timeout'          => $r['timeout'],
                'ignore_errors'    => $r['ignore_errors'],
            );

            if( isset($r['http']) && is_array($r['http']) ){
                $arrContext['http'] = $r['http'] + $arrContext['http'];
            }

            if( !empty($r['headers']) && is_array($r['headers']) ){
                $arrContext['http']['header'] = '';
                foreach($r['headers'] as $hk => $hv){
                    $arrContext['http']['header'] .= $hk.': '.$hv."\r\n";
                }
                $arrContext['http']['header'] = trim($arrContext['http']['header']);
            }

            return $arrContext;
        }

        public function fopen_request($url,$r){
            $handle = fopen($url, 'r');

            if( $handle === false ){
                static::$debug['fopen'] = 'no handle';
                return false;
            }

            static::stream_timeout($handle,$r['timeout']);

            $strResponse = $this->ReadHandle($handle);
            $theHeaders = static::StreamHeaders($handle);

            fclose($handle);

            $processedHeaders = static::processHeaders($theHeaders);
            $this->body = static::chunkTransferDecode($strResponse,$processedHeaders);

            return $this->ReturnRequest( $url, $r, $processedHeaders );
        }

        public static function ParseUrl($url){
            $arr_url = parse_url($url);
            if( is_array($arr_url) ){
                $arr_url += array('path'=>'');
            }elseif( \gp\tool::LoggedIn() ){
                trigger_error('invalid url: '.$url.' '.pre($url));
            }
            return $arr_url;
        }

        public static function FixScheme($url){
            preg_match('#^[a-z]+:#',$url,$match);

            if( empty($match) ){
                return 'http://'.$url;
            }

            $match[0] = strtolower($match[0]);
            if( $match[0] !== 'http:' && $match[0] !== 'https:' ){
                $url = substr($url,strlen($match[0]));
                $url = 'http://'.ltrim($url,'/');
            }

            return $url;
        }

        public function fsockopen_request($url,$r){
            $fsockopen_host = $this->url_array['host'];

            $iError = null;
            $strError = null;

            $port = 80;
            if( !empty($this->url_array['port']) ){
                $port = (int)$this->url_array['port'];
            }

            $handle = fsockopen($fsockopen_host, $port, $iError, $strError, $r['timeout']);

            if( $handle === false ){
                static::$debug['fsock'] = 'no handle';
                return false;
            }

            static::stream_timeout($handle,$r['timeout']);

            $strHeaders = $this->ReqHeader($r);
            fwrite($handle, $strHeaders);

            $strResponse = $this->ReadHandle($handle);
            fclose($handle);

            $process = static::processResponse($strResponse);
            $processedHeaders = static::processHeaders($process['headers']);
            $this->body = static::chunkTransferDecode($process['body'],$processedHeaders);

            return $this->ReturnRequest( $url, $r, $processedHeaders );
        }

        protected function ReqHeader($r){
            $requestPath = $this->url_array['path'] . ( isset($this->url_array['query']) ? '?' . $this->url_array['query'] : '' );
            if( empty($requestPath) ){
                $requestPath .= '/';
            }

            $strHeaders = strtoupper($r['method']) . ' ' . $requestPath . ' HTTP/' . $r['httpversion'] . "\r\n";
            $strHeaders .= 'Host: ' . $this->url_array['host'] . "\r\n";

            if( isset($r['user-agent']) ){
                $strHeaders .= 'User-Agent: ' . $r['user-agent'] . "\r\n";
            }

            if( !empty($r['headers']) && is_array($r['headers']) ){
                foreach($r['headers'] as $hk => $hv){
                    $strHeaders .= $hk . ': ' . $hv . "\r\n";
                }
            }

            $strHeaders .= "\r\n";

            return $strHeaders;
        }

        protected function ReadHandle($handle){
            $response = '';
            while( !feof($handle) ){
                $response .= fread($handle, 4096);
                if( static::$maxlength > -1 && strlen($response) > static::$maxlength ){
                    break;
                }
            }
            return $response;
        }

        protected function curl_request($url, $r){
            $handle = curl_init();
            if( $handle === false ){
                return false;
            }

            $timeout = (int) ceil($r['timeout']);
            curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($handle, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($handle, CURLOPT_URL, $url);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handle, CURLOPT_USERAGENT, $r['user-agent']);
            curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);

            if( defined('CURLOPT_PROTOCOLS') ){
                curl_setopt($handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            }

            curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $r['method']);
            curl_setopt($handle, CURLOPT_HEADERFUNCTION, array($this, 'curl_headers'));
            curl_setopt($handle, CURLOPT_WRITEFUNCTION, array($this, 'curl_body'));
            curl_setopt($handle, CURLOPT_HEADER, 0);

            if( $r['httpversion'] == '1.0' ){
                curl_setopt($handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
            }else{
                curl_setopt($handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            }

            curl_exec($handle);

            if( !$r['ignore_errors'] && curl_errno($handle) ){
                static::$debug['curl_error'] = curl_error($handle);
                curl_close($handle);
                return false;
            }

            curl_close($handle);

            $processedHeaders = static::processHeaders($this->headers);
            return $this->ReturnRequest($url, $r, $processedHeaders);
        }

        private function curl_headers($handle, $headers) {
            $this->headers .= $headers;
            return strlen($headers);
        }

        private function curl_body($handle, $data) {
            $data_length = strlen($data);
            $this->body .= $data;
            $this->bytes_written_total += $data_length;
            return $data_length;
        }

        public static function stream_timeout($handle,$time){
            if( !function_exists('stream_set_timeout') ){
                return;
            }

            $timeout = (int) floor($time);
            $utimeout = (int)(($time - $timeout) * 1000000);
            stream_set_timeout($handle, $timeout, $utimeout);
        }

        public function ReturnRequest($url, $r, $processedHeaders){

            $redir_location = $this->RedirectLocation($processedHeaders);
            if( $redir_location !== false ){
                if( $redir_location == $url ){
                    if( \gp\tool::LoggedIn() ){
                        msg('infinite redirection: '.$redir_location);
                    }
                    return false;
                }
                return $this->Redirect($redir_location,$r);
            }

            if( isset($processedHeaders['headers']['content-encoding']) && $processedHeaders['headers']['content-encoding'] == 'gzip' ){
                $this->Inflate();
            }

            return array(
                'headers'  => $processedHeaders['headers'],
                'body'     => $this->body,
                'response' => $processedHeaders['response'],
                'cookies'  => $processedHeaders['cookies']
            );
        }

        public function Inflate(){
            $body = @gzdecode($this->body);
            if( $body ){
                $this->body = $body;
                return true;
            }

            $body = @gzinflate(substr($this->body, 10));
            if( $body ){
                $this->body = $body;
                return true;
            }

            trigger_error('RemoteGet::Inflate() failed. Content: '.substr($this->body,0,200));
            return false;
        }

        public function Redirect($location,$r){
            if( $r['redirection']-- < 0 ){
                trigger_error('Too many redirects');
                return false;
            }

            static::$redirected = $location;
            static::$debug['Redir'] = 1;

            return $this->_get($location, $r);
        }

        public function RedirectLocation($headers){
            if( empty($headers['headers']['location']) ){
                return false;
            }

            $location = $headers['headers']['location'];
            if( is_array($location) ){
                do{
                    $location = array_pop($location);
                    $location = trim((string)$location);
                }while( count($location) && empty($location) );
            }

            $location = trim((string)$location);
            if( empty($location) ){
                return false;
            }

            if( substr($location,0,2) == '//' ){
                $location = $this->url_array['scheme'].':'.$location;
            }elseif( isset($location[0]) && $location[0] == '?' ){
                $location = $this->url_array['scheme'].'://'.rtrim($this->url_array['host'],'/').'/'.ltrim($this->url_array['path'],'/').$location;
            }elseif( isset($location[0]) && $location[0] == '/' ){
                $location = $this->url_array['scheme'].'://'.rtrim($this->url_array['host'],'/').$location;
            }elseif( preg_match('#^[a-z]+:#i',$location) ){
            }else{
                $urla = $this->url_array;
                unset($urla['query'], $urla['fragment']);

                if( empty($urla['path']) || $urla['path'] == '/' ){
                    $urla['path'] = $location;
                }elseif( substr($urla['path'],-1) != '/' ){
                    $urla['path'] = dirname($urla['path']) . '/' . ltrim($location,'/');
                }else{
                    $urla['path'] = rtrim($urla['path'],'/') . '/' . ltrim($location,'/');
                }

                $location = static::unparse_url($urla);
            }

            return $location;
        }

        public static function unparse_url($parsed_url) {
            $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
            $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
            $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
            $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
            $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
            $pass     = ($user || $pass) ? "$pass@" : '';
            $path     = isset($parsed_url['path']) ? '/'.ltrim($parsed_url['path'],'/') : '';
            $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
            $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

            return "$scheme$user$pass$host$port$path$query$fragment";
        }

        public static function chunkTransferDecode($body,$headers){
            if( !static::IsChunked($body,$headers) ){
                return $body;
            }

            $parsed_body = '';
            $body_original = $body;

            while ( true ) {
                $has_chunk = (bool) preg_match('/^([0-9a-f]+)[^\r\n]*\r\n/i', $body, $match);
                if( ! $has_chunk || empty($match[1]) ){
                    return $body_original;
                }

                $length = (int) hexdec($match[1]);
                $chunk_length = strlen($match[0]);
                $parsed_body .= substr($body, $chunk_length, $length);
                $body = substr($body, $length + $chunk_length);

                if( '0' === trim($body) ){
                    return $parsed_body;
                }
            }
        }

        public static function IsChunked($body, $headers){
            $body = trim((string)$body);

            if( empty($body) ){
                return false;
            }
            if( !isset($headers['headers']['transfer-encoding']) || 'chunked' != $headers['headers']['transfer-encoding'] ){
                return false;
            }
            if( !preg_match('/^([0-9a-f]+)[^\r\n]*\r\n/i',$body) ){
                return false;
            }

            return true;
        }

        public static function StreamHeaders($handle = null, bool $parseAsAssociative = false): array {
            $rawHeaders = [];

            if (is_array($handle)) {
                $rawHeaders = $handle;
            } elseif (is_resource($handle)) {
                $meta = stream_get_meta_data($handle);
                if (isset($meta['wrapper_data']) && is_array($meta['wrapper_data'])) {
                    $rawHeaders = $meta['wrapper_data'];
                }
            }

            return $parseAsAssociative ? self::parseRawHeaders($rawHeaders) : $rawHeaders;
        }

        private static function parseRawHeaders(array $headers): array {
            $parsed = [];
            foreach ($headers as $header) {
                if (!is_string($header)) continue;

                if (str_starts_with($header, 'HTTP/') || str_starts_with($header, ':')) {
                    $parsed['status'] = $header;
                    continue;
                }

                if (str_contains($header, ':')) {
                    [$key, $value] = explode(':', $header, 2);
                    $key = strtolower(trim($key));
                    $value = trim($value);
                    $parsed[$key][] = $value;
                }
            }

            return array_map(fn($v) => is_array($v) && count($v) === 1 ? $v[0] : $v, $parsed);
        }

        public static function processResponse(string $strResponse): array {
            $parts = preg_split("/\r?\n\r?\n/", $strResponse, 2);
            return [
                'headers' => $parts[0] ?? '',
                'body' => $parts[1] ?? '',
            ];
        }

        public static function processHeaders($headers) {
            $headers = static::HeadersArray($headers);
            $response = array('code' => 0, 'message' => '');
            $cookies = array();
            $newheaders = array();

            foreach( $headers as $tempheader ){
                if( false === strpos((string)$tempheader, ':') ){
                    $stack = explode(' ', (string)$tempheader, 3);
                    $stack[] = '';
                    list( , $response['code'], $response['message']) = $stack;
                    continue;
                }

                list($key, $value) = explode(':', (string)$tempheader, 2);
                $key = strtolower(trim($key));
                $value = trim($value);

                if( isset($newheaders[$key]) ){
                    if( !is_array($newheaders[$key]) ){
                        $newheaders[$key] = array($newheaders[$key]);
                    }
                    $newheaders[$key][] = $value;
                }else{
                    $newheaders[$key] = $value;
                }
            }

            static::$debug['Headers'] = count($newheaders);

            return array('response' => $response, 'headers' => $newheaders, 'cookies' => $cookies);
        }

        protected static function HeadersArray($headers){
            if ( is_string($headers) ) {
                $headers = str_replace("\r\n", "\n", $headers);
                $headers = preg_replace('/\n[ \t]/', ' ', $headers);
                $headers = explode("\n", $headers);
            }
            $headers = (array)$headers;
            $headers = array_filter($headers);

            return $headers;
        }

        public static function Debug($lang_key, $debug = array()){
            $debug = array_merge(static::$debug,$debug);
            return \gp\tool::Debug($lang_key, $debug);
        }
    }
}

namespace {
    class gpRemoteGet extends \gp\tool\RemoteGet{}
}