<?php

require_once 'lib_ini.php';

/**
 * Выполняет POST запрос
 * ожидает ответ сервера в JSON
 * @param $queryUrl
 * @param array $params
 * @return array|mixed
 */
function executeHTTPRequest ($queryUrl, array $params = array()) {
    $result = array();
    $queryData = http_build_query($params);

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));

    $curlResult = curl_exec($curl);
    curl_close($curl);

    if ($curlResult != '') $result = json_decode($curlResult, true);

    return $result;
}

function requestCodeUrl ($domain) {
    return 'https://' . $domain . '/oauth/authorize/' .
        '?client_id=' . urlencode(APP_ID);
}

function requestAccessToken ($code, $server_domain) {
    $url = 'https://' . $server_domain . '/oauth/token/?' .
        'grant_type=authorization_code'.
        '&client_id='.urlencode(APP_ID).
        '&client_secret='.urlencode(APP_SECRET_CODE).
        '&code='.urlencode($code);
    return executeHTTPRequest($url);
}

function refreshAccessToken ($refresh_token, $server_domain) {
    $url = 'https://' . $server_domain . '/oauth/token/?' .
        'grant_type=refresh_token'.
        '&client_id='.urlencode(APP_ID).
        '&client_secret='.urlencode(APP_SECRET_CODE).
        '&refresh_token='.urlencode($refresh_token);
    return executeHTTPRequest($url);
}

function executeREST ($rest_url, $method, $params, $access_token) {
    $url = $rest_url.$method.'.json';
    return executeHTTPRequest($url, array_merge($params, array("auth" => $access_token)));
}

/*
 * Произвиодит валидацию массива,
 * который содержит описание секции ini файла с авторизационным токеном
 * на предмет синтаксиса ini файла (наличие секции и ключей)
 */
function validateAuthTokenSectionSyntax ($section) {
	if (!is_array($section)) $section=[];
	if (!isset($section['token'])) $section['token']='';
	if (!isset($section['date'])) $section['date']=0;
	return $section;
}

/**
 * Грузит авторизационные токены из ini файла
 */
function loadAuthTokens () {
	global $auth_ini;
	$sections=['access','refresh'];

	//если ини файла нет - создаем пустой набор "протухших" данных
	if (file_exists($auth_ini))
		$data=parse_ini_file($auth_ini,true);
	else
		$data=[];

	//var_dump($data);

	foreach ($sections as $section) {
		$data[$section]=validateAuthTokenSectionSyntax(
			isset($data[$section])?
				$data[$section]:
				null
		);
	}
	return $data;
}

/**
 * Ищет в ответе сервера токены (access или refresh)
 * возвращает секцию для ini
 * @param $token string имя токена/секции (access или refresh)
 * @param $response array ответ сервера для поиска токенов в нем
 * @return array|null вовзращает или массив-секцию или null
 */
function fetchResponseToken($token, $response) {
	if (isset($response[$token.'_token'])) {
		return [
			'token'=>$response[$token.'_token'],
			'date'=>time()
		];
	}
	return null;
}

function fetchResponseTokens(&$tokens,$response) {
	global $auth_ini;

	$is_updated=false;
	$sections=['access','refresh'];

	foreach ($sections as $section) {
		if (!is_null($new_data=fetchResponseToken($section,$response))) {
			$tokens[$section]=$new_data;
			$is_updated=true;
		}
	}

	if ($is_updated) save_ini_file($tokens,$auth_ini);
	//var_dump($tokens);
	return $tokens;
}

/**
 * Возвращает возраст (в секундах) токена в секции
 */
function getTokenAge($section) {
	//если токен пустой, то возвращаем его возраст как будто он устарел
	if (!strlen($section['token'])) return time();
	//иначе просто возвращаем его фактический возраст
	return time()-$section['date'];
}

function checkRestAuth() {
	$tokens = loadAuthTokens();
	//для начала нам надо понять не протух ли наш имеющийся токен авторизации
	//битрикс выдает токен на час, но мы на всякий пожарный ограничим возраст 50ю минутами
	if (getTokenAge($tokens['access'])<3000) return $tokens;

	//раз дошли до сюда, значит авторизационный токен протух. нужно обновлять
	//отсюда 2 пути:
	//  - у нас есть токен обновления
	//  - или он тоже протух

	if (getTokenAge($tokens['refresh'])<86400*28) {

		//echo "Gettin auth token from refresh:<pre>";
		//print_r($_REQUEST);
		//echo "</pre><br/>";

		$response=refreshAccessToken($tokens['refresh']['token'],'oauth.bitrix.info');
		fetchResponseTokens($tokens,$response);
		//return $tokens;
	}

	return $tokens;
}

/**
 * Выводит на экран инфо о токене
 * @param $section array секция INI файла с токеном
 */
function renderTokenData($section,$maxage=3600) {
	echo '<b>Токен:</b> '.substr($section['token'],0,8).'...<br>';
	echo '<b>Отпечаток:</b> '.$section['date'].'<br>';
	echo '<b>Возраст:</b> '.gmdate("zд H:i:s", getTokenAge($section)).'<br>';
	echo '<b>Истекает:</b> '.gmdate("zд H:i:s", $section['date']+$maxage-time()).'<br>';
}