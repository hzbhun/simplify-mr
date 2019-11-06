<?php
require("vendor/autoload.php");

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Pool;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

$simplifyMr = new SimplifyMr();
$simplifyMr->saveMr();

class SimplifyMr
{
    public static $usersInSpreadSheet = [
        'hegedus.zoltan' => 'G',
        'kelemen.gabor' => 'H',
        'feher.zoltan' => 'E',
        'hajdu.robert' => 'F'
    ];

    public function saveMr()
    {
        $this->ackRequest();

        $input = $this->parseInput($_POST['text']);
        $results = [];
        $pool = new Pool(new Client(), $this->getRequests($input), [
            'concurrency' => 2,
            'options' => [
                'connect_timeout' => 5,
                'timeout' => 10,
            ],
            'fulfilled' => function (ResponseInterface $response, $index) use (&$results) {
                $results[$index] = $response;
            },
            'rejected' => function (RequestException $e, $index) use (&$results) {

            },
        ]);
        $pool->promise()->wait();

        print_r(array_keys($results));
    }

    protected function ackRequest()
    {
        echo "";
        ob_flush();
        flush();
    }

    protected function parseInput($slackText)
    {
        $params = explode(" ", $slackText);

        return [
            'mr' => $params[0] ?? '',
            'ticket' => $params[1] ?? '',
            'description' => isset($params[2]) ?  implode(' ', array_slice($params, 2)) : ''
        ];
    }

    protected function getRequests(array $input)
    {
        return [
            'spreadsheet' => $this->getSpreadSheetRequest($input),
            'notification' => $this->getSlackNotificationRequest($input)
        ];
    }

    protected function getSpreadSheetRequest(array $input)
    {
        $data = array_merge($input, [
            'owner' => self::$usersInSpreadSheet[$_POST['user_name']] ?? '',
            'desc' =>  $input['description']
        ]);
        $spreadSheetUri = new Uri(getenv("SHEET_URL"));

        return new Request(
            "GET",
            Uri::withQueryValues($spreadSheetUri, $data)
        );
    }

    protected function getSlackEndCommandRequest($url)
    {
        $data = [
            'text' => 'OKÉ',
            "response_type" => "ephemeral"
        ];

        return $this->getSlackRequest($url, $data);
    }

    protected function getSlackNotificationRequest(array $input)
    {
        $data = ['text' => ':CR: please: ' . $input['mr']];

        return $this->getSlackRequest(getenv("SLACK_URL_TESZT"), $data);
    }

    protected function getSlackRequest($url, array $data)
    {
        return new Request(
            "POST",
            $url,
            ['content-type' => 'application/json'],
            json_encode($data)
        );
    }
}
