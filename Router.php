<?php namespace muvo\yii\mikrotik;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Communicator;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;
use PEAR2\Net\RouterOS\SocketException;
use PEAR2\Net\Transmitter\NetworkStream;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidCallException;
use yii\helpers\VarDumper;

class Router extends Component
{
    public $hostname;
    public $username;
    public $password;
    public $port;
    public $ssl = false;
    public $persist = false;
    public $timeout = 25;
    protected $_client;

    public function init(){
        parent::init();

        set_time_limit(1800);

        if(empty($this->port))
            $port = $this->ssl ? 8729 : 8728;
        else
            $port = $this->port;

        try{
            $this->_client = new Client($this->hostname,
                $this->username,
                $this->password,
                $port,
                $this->persist,
                $this->timeout,
                $this->ssl
                    ? NetworkStream::CRYPTO_TLS
                    : NetworkStream::CRYPTO_OFF
            );
        }catch (SocketException $e){
            \Yii::error($e->getMessage(),__METHOD__);
            throw new Exception($e->getMessage(),$e->getCode(),$e);
        }

        if(!empty(\Yii::$app->charset))
            $this->_client->setCharset([
                Communicator::CHARSET_REMOTE => 'windows-1251',
                Communicator::CHARSET_LOCAL => \Yii::$app->charset,
            ]);
    }

    public static function getRouter(){
        return \Yii::$app->get('mikrotik');
    }

    public function getClient(){
        return self::getRouter();
    }

    public function sync(Request $request){
        \Yii::trace(VarDumper::dumpAsString($request),__METHOD__);
        $responses = $this->_client->sendSync($request);
        foreach($responses as $response)
            switch($response->getType()){
                case Response::TYPE_DATA:
                    \Yii::trace(VarDumper::dumpAsString($response),__METHOD__);
                    break;

                case Response::TYPE_FINAL:
                    \Yii::info(VarDumper::dumpAsString($response),__METHOD__);
                    break;

                case Response::TYPE_ERROR:
                    \Yii::warning(VarDumper::dumpAsString($response),__METHOD__);
                    throw new InvalidCallException($response->getProperty('message'));
                    break;

                case Response::TYPE_FATAL:
                    \Yii::error(VarDumper::dumpAsString($response),__METHOD__);
                    break;
            }

        return $responses;
    }
}
