<?php
echo "OK";
ob_flush();

$users = [
    'hegedus.zoltan' => 'G',
    'kelemen.gabor' => 'H',
    'feher.zoltan' => 'E',
    'hajdu.robert' => 'F'
];

$params = explode(" ", $_POST['text']);
$array = [
    'mr' => $params[0] ?? '',
    'ticket' => $params[1] ?? '',
    'desc' => isset($params[2]) ?  implode(' ', array_slice($params, 2)) : '',
    'owner' => $users[$_POST['user_name']] ?? ''
];
file_get_contents(getenv("SHEET_URL") . "?" . http_build_query($array));

$data = array('text' => ':CR: please: ' . $array['mr']);
$options = array(
    'http' => array(
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
    )
);
$context = stream_context_create($options);
file_get_contents(getenv("SLACK_URL"), false, $context);
