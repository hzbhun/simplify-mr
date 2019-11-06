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
    const SPREADSHEET_REQUEST = 'spreadsheet';
    const NOTIFICATION_REQUEST = 'notification';

    public static $usersInSpreadSheet = [
        'hegedus.zoltan' => 'G',
        'kelemen.gabor' => 'H',
        'feher.zoltan' => 'E',
        'hajdu.robert' => 'F'
    ];

    private $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function saveMr()
    {
        $this->ackRequest();

        $input = $this->parseInput($_POST['text']);
        $results = [];
        $pool = new Pool($this->client, $this->getRequests($input), [
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

        if (count($results) == 2) {
            $this->client->send($this->getSlackEndCommandRequest($_POST['response_url'], 'Sikeresen hozzáadva a táblázathoz.'));
        } else {
            $this->client->send($this->getSlackEndCommandRequest($_POST['response_url'], 'Valami probléma történt...'));
        }
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
            self::SPREADSHEET_REQUEST => $this->getSpreadSheetRequest($input),
            self::NOTIFICATION_REQUEST => $this->getSlackNotificationRequest($input)
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

    protected function getSlackEndCommandRequest($url, $text)
    {
        $data = [
            'text' => $text,
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
