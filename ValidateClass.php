<?php

/**
* 验证器类
*/
class ValidateClass
{
    /**
     * 验证规则：required,nullable,min,max,email,phone,string,integer,url,ip,json,in,not_in,regex,bool,dateFormat
     */

    public $errMsg = [];

    protected static $i = null;

    public static function I()
    {
        if (self::$i === null) {
            self::$i = new self();
        }
        return self::$i;
    }

    /**
     * 映射出错误消息
     * @Date   2017-08-03
     * @param  string     $ruleKey    要验证的字段
     * @param  string     $key        要验证的规则
     * @param  string     $parameters 验证参数
     * @return string                 错误提示
     */
    protected function mapping($ruleKey, $key, $parameters = '')
    {
        if (!empty($parameters)) {
            $parameters = is_array($parameters) ? implode(',', $parameters) : $parameters;
        }

        $map = [
            'required'  => '不能为空',
            'min'       => '最小值为：' . $parameters,
            'max'       => '最大值为：' . $parameters,
            'email'     => '必须为邮箱',
            'phone'     => '必须为11位号码电话',
            'string'    => '必须为字符串',
            'integer'   => '必须为整数',
            'url'       => '必须为网址',
            'ip'        => '必须为IP',
            'json'      => '必须为json',
            'in'        => '必须在' . $parameters . '区间',
            'not_in'    => '不能在' . $parameters . '区间',
            'regex'     => '验证失败',
            'bool'      => '必须为布尔值',
            'dateFormat'    => '时间格式必须为：' . $parameters,
        ];
        return $ruleKey . $map[$key];
    }

    /**
     * 验证
     */
    public function make($data, $rule, $msg = [])
    {
        foreach ($rule as $ruleKey => $ruleVlue) {
            if (!in_array($ruleKey, array_keys($data))) {
                return $this->response('字段' . $ruleKey . '不存在！');
            }
            $ruleArr = explode('|', $ruleVlue);
            $methods = get_class_methods('Validate');
            //删除验证函数(required)前面的其它函数
            array_splice($methods, 0, 6);
            foreach ($ruleArr as $rules) {
                $params = explode(':', $rules);
                if (!in_array($params[0], $methods)) {
                    return $this->response('不存在该验证规则：' . $params[0]);
                }
                if ($params[0] === 'nullable') {
                    if (!$this->required($data[$ruleKey])) {
                        break;
                    } else {
                        continue;
                    }
                }
                $parameters = isset($params[1]) ? $params[1] : '';
                if (in_array(count($params), [1,2])) {
                    $res = $this->$params[0]($data[$ruleKey], $parameters);
                } else {
                    return $this->response('验证规则参数有误：' . json_encode($params));
                }
                if ($res === false) {
                    if (is_array($msg) && !empty($msg)) {
                        return $this->response($msg[$ruleKey]);
                    } else {
                        $notice = $this->mapping($ruleKey, $params[0], $parameters);
                        return $this->response($notice);
                    }
                }
            }
        }
        return $this;
    }

    /**
     * 错误报告
     */
    private function response($msg)
    {
        $this->errMsg[] = $msg;
        return $this;
    }

    /**
     * 验证成功或失败
     */
    public function fails()
    {
        return count($this->errMsg) > 0;
    }

    /**
     * 失败信息
     */
    public function errors()
    {
        return $this->errMsg;
    }

    /**
     * 必填
     */
    protected function required($value, $parameters = '')
    {
        if (is_null($value)) {
            return false;
        } elseif (is_string($value) && trim($value) === '') {
            return false;
        } elseif ((is_array($value) || $value instanceof Countable) && count($value) < 1) {
            return false;
        }
        return true;
    }

    /**
     * 非必填，允许为空，如果不为空则触发后面的验证规则
     */
    protected function nullable($value, $parameters = '')
    {
        return !$this->required($value, $parameters = '');
    }

    /**
     * 最小值
     */
    protected function min($value, $parameters)
    {
        if (mb_strlen($value, 'UTF-8') < $parameters) {
            return false;
        }
        return true;
    }

    /**
     * 最小值
     */
    protected function max($value, $parameters)
    {
        if (mb_strlen($value, 'UTF-8') > $parameters) {
            return false;
        }
        return true;
    }

    /**
     * 邮箱
     */
    protected function email($value, $parameters = '')
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * 手机号码
     */
    protected function phone($value, $parameters = '')
    {
        return preg_match('/^1[34578][0-9]{9}$/', $value) > 0;
    }

    /**
     * 字符串
     */
    protected function string($value, $parameters = '')
    {
        return is_string($value);
    }

    /**
     * 整数
     */
    protected function integer($value, $parameters = '')
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * url
     */
    protected function url($value, $parameters = '')
    {
        if (! is_string($value)) {
            return false;
        }

        /*
         * (c) Fabien Potencier <fabien@symfony.com> http://symfony.com
         */
        $pattern = '~^
            ((aaa|aaas|about|acap|acct|acr|adiumxtra|afp|afs|aim|apt|attachment|aw|barion|beshare|bitcoin|blob|bolo|callto|cap|chrome|chrome-extension|cid|coap|coaps|com-eventbrite-attendee|content|crid|cvs|data|dav|dict|dlna-playcontainer|dlna-playsingle|dns|dntp|dtn|dvb|ed2k|example|facetime|fax|feed|feedready|file|filesystem|finger|fish|ftp|geo|gg|git|gizmoproject|go|gopher|gtalk|h323|ham|hcp|http|https|iax|icap|icon|im|imap|info|iotdisco|ipn|ipp|ipps|irc|irc6|ircs|iris|iris.beep|iris.lwz|iris.xpc|iris.xpcs|itms|jabber|jar|jms|keyparc|lastfm|ldap|ldaps|magnet|mailserver|mailto|maps|market|message|mid|mms|modem|ms-help|ms-settings|ms-settings-airplanemode|ms-settings-bluetooth|ms-settings-camera|ms-settings-cellular|ms-settings-cloudstorage|ms-settings-emailandaccounts|ms-settings-language|ms-settings-location|ms-settings-lock|ms-settings-nfctransactions|ms-settings-notifications|ms-settings-power|ms-settings-privacy|ms-settings-proximity|ms-settings-screenrotation|ms-settings-wifi|ms-settings-workplace|msnim|msrp|msrps|mtqp|mumble|mupdate|mvn|news|nfs|ni|nih|nntp|notes|oid|opaquelocktoken|pack|palm|paparazzi|pkcs11|platform|pop|pres|prospero|proxy|psyc|query|redis|rediss|reload|res|resource|rmi|rsync|rtmfp|rtmp|rtsp|rtsps|rtspu|secondlife|service|session|sftp|sgn|shttp|sieve|sip|sips|skype|smb|sms|smtp|snews|snmp|soap.beep|soap.beeps|soldat|spotify|ssh|steam|stun|stuns|submit|svn|tag|teamspeak|tel|teliaeid|telnet|tftp|things|thismessage|tip|tn3270|turn|turns|tv|udp|unreal|urn|ut2004|vemmi|ventrilo|videotex|view-source|wais|webcal|ws|wss|wtai|wyciwyg|xcon|xcon-userid|xfire|xmlrpc\.beep|xmlrpc.beeps|xmpp|xri|ymsgr|z39\.50|z39\.50r|z39\.50s))://                                 # protocol
            (([\pL\pN-]+:)?([\pL\pN-]+)@)?          # basic auth
            (
                ([\pL\pN\pS-\.])+(\.?([\pL]|xn\-\-[\pL\pN-]+)+\.?) # a domain name
                    |                                              # or
                \d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}                 # an IP address
                    |                                              # or
                \[
                    (?:(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){6})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:::(?:(?:(?:[0-9a-f]{1,4})):){5})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){4})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,1}(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){3})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,2}(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){2})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,3}(?:(?:[0-9a-f]{1,4})))?::(?:(?:[0-9a-f]{1,4})):)(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,4}(?:(?:[0-9a-f]{1,4})))?::)(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,5}(?:(?:[0-9a-f]{1,4})))?::)(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,6}(?:(?:[0-9a-f]{1,4})))?::))))
                \]  # an IPv6 address
            )
            (:[0-9]+)?                              # a port (optional)
            (/?|/\S+|\?\S*|\#\S*)                   # a /, nothing, a / with something, a query or a fragment
        $~ixu';

        return preg_match($pattern, $value) > 0;
    }

    /**
     * ip
     */
    protected function ip($value, $parameters = '')
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * json
     */
    protected function json($value, $parameters = '')
    {
        if (!is_scalar($value) && !method_exists($value, '__toString')) {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * 在目标区间
     */
    protected function in($value, $parameters)
    {
        $parameters = is_array($parameters) ? $parameters : explode(',', $parameters);
        if (is_array($value)) {
            foreach ($value as $element) {
                if (is_array($element)) {
                    return false;
                }
            }
            return count(array_diff($value, $parameters)) == 0;
        }

        return !is_array($value) && in_array((string)$value, $parameters);
    }

    /**
     * 不在目标区间
     */
    protected function not_in($value, $parameters)
    {
        return !$this->in($value, $parameters);
    }

    /**
     * 正则验证
     */
    protected function regex($value, $parameters)
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }
        if (count($parameters) < 1) {
            return false;
        }

        return preg_match($parameters[0], $value) > 0;
    }

    /**
     * bool
     */
    protected function bool($value, $parameters)
    {
        $acceptable = [true, false, 0, 1, '0', '1'];

        return in_array($value, $acceptable, true);
    }

    /**
     * 时间按指定格式验证
     */
    protected function dateFormat($value, $parameters)
    {
        if (count($parameters) < 1) {
            return false;
        }
        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }
        $date = date($parameters, strtotime($value));
        return $date && $date == $value;
    }

}
