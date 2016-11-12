<?php namespace muvo\yii\mikrotik\models;

use muvo\yii\ip\address\IPv4;
use PEAR2\Net\RouterOS\Query;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;
use yii\base\InvalidCallException;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\helpers\VarDumper;

class ListItem extends Model
{
    private $_id;
    public $list;
    private $_address;
    public $comment;
    public $disabled = 'no';
    public $timeout = 0;
    protected static $_router;

    public static function getRouter(){
        return isset(self::$_router)
            ? self::$_router
            : \Yii::$app->get('mikrotik');
    }

    public function setRouter($config){
        return self::$_router = $config;
    }

    public static function find($condition=array()){
        $request = new Request('/ip/firewall/address-list/print');
        if(!empty($condition)){
            $query = null;
            if(is_array($condition)&&ArrayHelper::isAssociative($condition))
                foreach($condition as $key=>$value){
                    if(!$query)
                        $query = Query::where($key,$value,Query::OP_EQ);
                    elseif($query instanceof Query)
                        $query->andWhere($key,$value,Query::OP_EQ);
                }
            elseif($condition instanceof Query)
                $query = $condition;

            $request->setQuery($query);
        }

        $items = array();
        foreach(self::getRouter()->sync($request) as $response)
            if($response->getType()===Response::TYPE_DATA)
                $items[] = \Yii::createObject(self::className(),[[
                    'router' => self::getRouter(),
                    'id' => $response->getProperty('.id'),
                    'list' => $response->getProperty('list'),
                    'address' => $response->getProperty('address'),
                    //'dynamic' => $response->getProperty('dynamic'),
                    'disabled' => $response->getProperty('disabled'),
                ]]);

        return $items;
    }

    public static function get($id){
        $list=self::find(['.id'=>$id]);
        if(count($list)===0)
            \Yii::info('Nothing found!',__METHOD__);
        elseif(count($list)>1){
            \Yii::warning('More than one result found!');
            \Yii::warning(VarDumper::dumpAsString($list),__METHOD__);
        }

        return isset($list[0]) ? $list[0] : null;
    }

    public static function add($attributes){
        $request = new Request('/ip/firewall/address-list/add');
        foreach($attributes as $key=>$value)
            $request->setArgument($key,$value);
        $response = self::getRouter()->sync($request);
        \Yii::trace(VarDumper::dumpAsString($response),__METHOD__);

        if($response[0]->getType()!==Response::TYPE_FINAL)
            throw new InvalidCallException('Can\'t add item to address list',500);

        return $response[0]->getProperty('ret');
    }

    public static function set($id,$attributes=array()){
        $request = new Request('/ip/firewall/address-list/set');
        $request->setArgument('.id',$id);
        foreach($attributes as $key=>$value)
            $request->setArgument($key,$value);

        $response = self::getRouter()->sync($request);
        \Yii::trace(VarDumper::dumpAsString($response),__METHOD__);

        if($response[0]->getType()!==Response::TYPE_FINAL)
            throw new InvalidCallException('Can\'t modify item in address list',500);

        return true;
    }

    public static function remove($id){
        $request = new Request('/ip/firewall/address-list/remove');
        $request->setArgument('.id',$id);
        $responses = self::getRouter()->sync($request);

        \Yii::trace(VarDumper::dumpAsString($responses),__METHOD__);
        foreach($responses as $response)
            return $response->getType()===Response::TYPE_FINAL
                ? true
                : false;

        return null;
    }

    public function fields(){
        return [
            '.id' => function($model){
                    return $model->_id;
                },
            'address' => function($model){
                    return $model->address->cidr;
                },
            'list',
            'comment',
            'timeout',
            'disabled',
        ];
    }

    public function extraFields(){
        return [ 'dynamic', 'id' ];
    }

    public function safeAttributes(){
        return [
            'address',
            'list',
            'comment',
            'timeout',
            'disabled',
        ];
    }

    public function setId($value){
        return $this->_id = $value;
    }

    public function getId(){
        return $this->_id;
    }

    public function setAddress($address){
        return $this->_address = IPv4::create($address);
    }

    public function getAddress(){
        return $this->_address;
    }

    public function getDynamic(){
        return (bool) $this->timeout > 0;
    }

    public function save(){
        return isset($this->_id)
            ? self::set($this->_id,$this->toArray())
            : self::add($this->toArray());
    }

    public function delete(){
        return self::remove($this->id);
    }
}
