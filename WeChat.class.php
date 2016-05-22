<?php

/**
 * PHP操作微信公众平台接口 类
 */

class WeChat {

	private $_appid;
	private $_appsecret;
	private $_token;

	public function __construct($appid, $appsecret, $token) {
		$this->_appid = $appid;
		$this->_appsecret = $appsecret;
		$this->_token = $token;
	}

	/**
	 * 处理分析接收到的消息数据
	 */
	public function doRequest() {
		// 接收请求数据
		// 不是典型 的KEY/Value形式的请求主体数据，可以使用下面的元素来获取到

		$xml_str = $GLOBALS['HTTP_RAW_POST_DATA'];
		// 使用simpleXML进行处理
		// 安全考虑，不去解析外部的XML实体，防止xml注入
		libxml_disable_entity_loader(true);
		$msg = simplexml_load_string($xml_str, 'SimpleXMLElement', LIBXML_NOCDATA);
		// 针对于不同的消息类型做不同的处理方法
		switch($msg->MsgType) {
			case 'event':
				// 判断事件类型
				if ($msg->Event == 'subscribe') {
					// 订阅（关注）事件
					$this->_dosubScribe($msg);//调用该函数处理订阅事件
				}
				break;
			case 'text':
				$this->_doText($msg);
				break;
			case 'image':
				$this->_doImage($msg);
				break;
		}
	}
	// 响应发送数据模板
	private $_template = array(
		'text' => <<<XML
<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[text]]></MsgType>
<Content><![CDATA[%s]]></Content>
</xml>
XML
,
		);
	/**
	 * 处理图片类型的消息
	 */
	private function _doImage($msg) {
		$content = '你所上传的图片的URL地址为: ' . $msg->PicUrl;
		// 做响应
		$template = $this->_template['text'];
		$response_content = sprintf($template, $msg->FromUserName, $msg->ToUserName, time(), $content);
		echo $response_content;
		file_put_contents('./media_id.txt', $msg->MediaId);

	}
	/**
	 * 处理文本消息
	 */
	private function _doText($msg) {
		// 具体的响应数据，依赖于数据库中存储的信息
		$content_default = '输入下面的内容，获取索要的信息' . "\n";
		$content_default .= '<PHP> 获取PHP开班信息' . "\n";
		$content_default .= '<Java> 获取PHP开班信息' . "\n";
		$content_default .= '<IOS> 获取PHP开班信息' . "\n";
		switch(strtoupper($msg->Content)) {
			case 'PHP':
				$content = 'PHP, 世界上最流行的WEB编程语言';
				break;
			case 'Java':
				$content = 'Java, 是一种可以撰写跨平台应用软件的面向对象的程序设计语言';
				break;
			case 'IOS':
				$content = 'PHP, 移动端开发利器，高大上的编程语言';
				break;
			case '?':
			case 'HELP':
			case '帮助':
				$content = $content_default;
				break;
			default:
				// 利用小黄鸡，完成智能聊天
				$url = "http://www.xiaohuangji.com/ajax.php";
				// post请求
				$data['para'] = $msg->Content;
				$content = $this->_POST($url, $data, false);
		}

		// 做响应
		$template = $this->_template['text'];
		$response_content = sprintf($template, $msg->FromUserName, $msg->ToUserName, time(), $content);
		echo $response_content;

	}
	/**
	 * 用于处理订阅（关注）事件的方法
	 */
	private function _dosubScribe($msg) {
		// 拼凑 符合文本信息的XML文档
		$template = $this->_template['text'];
		$content = '感谢关注！';
		$response_content = sprintf($template, $msg->FromUserName, $msg->ToUserName, time(), $content);
		echo $response_content;
	}

	/**
	 * 第一次接入校验
	 */
	public function firstCheck() {
		// 校验
		if ($this->_checkSignature()) {
			echo $_GET['echostr'];
		}
	}

	/**
	 * 验证Signature,用于验证请求是否来源于微信服务器
	 */
	private function _checkSignature() {
		// 排序需要加密的字符串
		$arr[] = $this->_token;
		$arr[] = $_GET['timestamp'];
		$arr[] = $_GET['nonce'];
		sort($arr, SORT_STRING);
		// 连接
		$arr_str = implode($arr);
		// 加密
		$sha1_str = sha1($arr_str);

		// 比较
		if ($sha1_str == $_GET['signature']) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * 生成QRCODE
	 *
	 */
	public function getQRCode($scene_id, $type='QR_SCENE', $expire_seconds=604800) {
		// 获取access_token
		$access_token = $this->_getAccessToken();
		// 获取 ticket
		$ticket = $this->_getQRCodeTicket($scene_id, $type='QR_SCENE', $expire_seconds=604800);
		// 利用ticket换取图片内容
		$image_content = $this->_getQRCodeImage($ticket);
		return $image_content;
	}

	private function _getQRCodeImage($ticket) {
		$api_url = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . urlencode($ticket);
		$response_content = $this->_GET($api_url);
		return $response_content;
	}
	/**
	 * [_getQRCodeTicket description]
	 * @param int(string) $scene_id
	 * @param [type] $type qrcode的类型
	 * @param int $expire_seconds 临时二维码需要
	 * @return [type]       [description]
	 */
	private function _getQRCodeTicket($scene_id, $type='QR_SCENE', $expire_seconds=604800) {
		$api_url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=' . $this->_getAccessToken();

		// Post 数据
		switch($type) {// 判断类型
			case 'QR_LIMIT_SCENE':
				$data['action_name'] = 'QR_LIMIT_SCENE';
				$data['action_info']['scene']['scene_id'] = $scene_id;
				break;
			case 'QR_SCENE':
			default:
				$data['action_name'] = 'QR_SCENE';
				$data['action_info']['scene']['scene_id'] = $scene_id;
				$data['expire_seconds'] = $expire_seconds;
		}
		$data = json_encode($data);

		// 发请求获取ticket
		$response_content = $this->_POST($api_url, $data);
		$response_data = json_decode($response_content);
		if (isset($response_data->errcode)) {
			trigger_error('QRCode 的 Ticket 获取失败, 原因为' . $response_data->errmsg);
			return false;
		}
		return $response_data->ticket;
	}


	/**
	 * 获取access_token
	 */
	private function _getAccessToken() {
		// access_token缓存文件中地址
		$access_token_file = './access_token';
		if (file_exists($access_token_file)) {// 是否存在
			// 是否过期
			$content = file_get_contents($access_token_file);
			$data = explode('::', $content);
			if (time()-filemtime($access_token_file) <= $data[0]){
				// 没有过期
				return $data[1];
			}
		}

		// 获取api请求地址
		$api_url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s';
		$api_url = sprintf($api_url, $this->_appid, $this->_appsecret);

		// get请求
		$response_content = $this->_GET($api_url);
		// 处理响应数据
		$response_data = json_decode($response_content);
		// 记录到access_token缓存文件中
		file_put_contents($access_token_file, $response_data->expires_in.'::'.$response_data->access_token);
		return $response_data->access_token;
	}



	/**
	 * 发送GET请求
	 * @param string $url URL
	 * @param bool $https 是否为https请求
	 * @return string 响应结果
	 */
	private function _GET($url, $https=true) {
		return $this->_request($url);
	}

	/**
	 * [_POST description]
	 * @param  [type]  $url   [description]
	 * @param  [type]  $data  [description]
	 * @param  boolean $https [description]
	 * @return [type]         [description]
	 */
	private function _POST($url, $data, $https=true) {
		return $this->_request($url, $https, 'POST', $data);
	}


	/**
	 * [_request description]
	 * @param  [type]  $url   [description]
	 * @param  boolean $https [description]
	 * @param  string  $type  [description]
	 * @param  array   $data  [description]
	 * @return [type]         [description]
	 */
	private function _request($url, $https=true, $type='GET', $data=null) {
		$curl = curl_init();

		// 设定选项
		curl_setopt($curl, CURLOPT_URL, $url);
		// 请求时通常会携带选项，代理信息，referer来源信息
		// 请求代理信息
		$useragent = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.90 Safari/537.36';
		curl_setopt($curl, CURLOPT_USERAGENT, $useragent);
		// 自动生成请求来源
		curl_setopt($curl, CURLOPT_AUTOREFERER, true);
		// 请求超时时间
		curl_setopt($curl, CURLOPT_TIMEOUT, 30);
		// 是否获取响应头
		curl_setopt($curl, CURLOPT_HEADER, false);
		// 是否返回响应结果
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		if ($https) {// 是HTTPS请求
			// https相关：是否对服务器的ssl验证
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			// https相关：ssl主机验证方式
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1);
		}
		if ($type == 'POST') {// post请求
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}

		// 发出请求
		$response_content = curl_exec($curl);

		if ($response_content === false) {
			trigger_error('请求不能完成，所请求的URL为：' . $url . "\n" . 'curl错误为：' . curl_error($curl), E_USER_ERROR);
			curl_close($curl);
			return false;
		}

		curl_close($curl);
		return $response_content;
	}
}
