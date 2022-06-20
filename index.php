<?php

namespace app;

use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;

require 'vendor/autoload.php';

class JsonAPI {

    const USER_AGENT = 'Mozilla/5.0 (compatible; Bot/1.0)';

    const API_BASE_URI = 'https://supercooldesign.co.uk/api/technical-test/';

    const HTML_OUTPUT_DIR = 'public';

    public $events = [];

    public $venues = [];

    public $instances = [];

    protected $guzzle;
    
    public function __construct()
    {
        $this->guzzle = new GuzzleHttp\Client(
            [
                'base_uri'  => $this::API_BASE_URI,
                'cookies'   => true,
                'timeout'   => 5.0,
                'headers'   => [
                    'User-Agent'         => $this::USER_AGENT,
                    'Accept-Language'     => 'en-GB',
                    'Connection'         => 'keep-alive',
                    'Pragma'             => 'no-cache',
                    'Cache-Control'     => 'no-cache',
                    'Content-Type'         => 'application/json',
                ]
            ]
        );
    }

    /**
     * @return void
     */
    public function getAllDataFromAPI(): void
    {
        $this->getEvents();
        $this->getVenues();
        $this->getInstances();
    }

    /**
     * @return void
     */
    public function getEvents(): void
    {
        $this->events = $this->getApiData('events');
    }

    /**
     * @return void
     */
    public function getVenues(): void
    {
        $this->venues = $this->getApiData('venues');
    }

    /**
     * @return void
     */
    public function getInstances(): void
    {
        $this->instances = $this->getApiData('instances');
    }

    /**
     * @param $data
     * @return string
     */
    public function getFirstDate($data) {
        sort($data);

        return $data[array_key_first($data)];
    }

    /**
     * @param $data
     * @return string
     */
    public function getNextDate($data) {
        sort($data);

        foreach ($data as $k => $date) {
            if (strtotime($date) >= time()) {
                return $date;
            }
        }

        return 'Event has now passed.';
    }

    /**
     * @param $data
     * @return string
     */
    public function getLastDate($data) {
        sort($data);

        return end($data);
    }

    /**
     * @param $id
     * @return string
     */
    public function getEventById($id) {
        $this->events = array_values($this->events);
        $key = array_search($id, array_column($this->events, 'id'), true);        
        if ($key) {
            $findArray = $this->events[$key];
            if ($findArray && isset($findArray['title'])) {
                return $findArray['title'];
            }
        }
    }

    /**
     * @return string
     */
    public function processCollectedData() {
        if (empty($this->events) || empty($this->instances) | empty($this->venues)) {
            return 'No events to show';
        }

        $outData = [];

        // Venues should be in alphabetical order
        usort($this->venues, function($a, $b) {
            return $a['title'] <=> $b['title'];
        });

        // "startSelling" date has passed, but the "stopSelling" date hasn't
        $this->events = array_filter($this->events, function($k) {
            if (time() >= strtotime($k['startSelling']) && strtotime($k['stopSelling']) >= time()) {
                return true;
            }

            return false;
        });

        foreach ($this->instances as $instance) {
            // Save dates
            $outData[$instance['event']['id']]['dates'][] = $instance['start'];

            // Save venue
            $outData[$instance['event']['id']]['venue'] = $instance['venue']['id'];

            // Count ADs
            if ($instance['attribute_audioDescribed'] === true) {
                if (!isset($outData[$instance['event']['id']]['ad'])) {
                    $outData[$instance['event']['id']]['ad'] = 1;
                } else {
                    $outData[$instance['event']['id']]['ad'] = (int) $outData[$instance['event']['id']]['ad'] + 1;
                }
            }
        }

        // Build formatted HTML
        $html = '<ul>';

        $isOpen = false;
        $looper = 1;
        foreach ($this->venues as $k => $venue) {
            foreach ($outData as $eventID => $data) {
                if ($data['venue'] === $venue['id']) {
                    if ($looper === 1) {
                        if ($isOpen) {
                            $html .= '</ul>';
                        }
                        $html .= '<li>' . $venue['title'] . ':<ul>';
                        $isOpen = true;
                    }

                    $html .= '<li>Event: ' . $this->getEventById($eventID) . '<br>';
                    $html .= 'id: ' . $eventID . '<br>';
                    $html .= 'First instance: ' . $this->getFirstDate($data['dates']) . ' <br>';
                    $html .= 'Next instance: ' . $this->getNextDate($data['dates']) . ' <br>';
                    $html .= 'Last instance: ' . $this->getLastDate($data['dates']) . ' <br>';
                    $html .= 'Instance count: ' . count($data['dates']) . ' <br>';
                    $html .= 'Audio Described instance count: ' . $data['ad'] . ' <br></li>';
                    $looper++;
                }
            }
            $looper = 1;
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * @param string $file
     * @return void
     */
    public function saveDataToHTML(string $file) {
        if (!file_exists(__DIR__ . DIRECTORY_SEPARATOR . $this::HTML_OUTPUT_DIR)) {
            mkdir(__DIR__ . DIRECTORY_SEPARATOR . $this::HTML_OUTPUT_DIR);
        }

        file_put_contents($this::HTML_OUTPUT_DIR . DIRECTORY_SEPARATOR . $file, $this->processCollectedData());
    }

    /**
     * @param string $requestType
     * @return array|void
     * @throws ServerException|GuzzleException
     */
    public function getApiData(string $requestType) {
        try {
            $response = $this->guzzle->request('GET', $requestType);
            return json_decode($response->getBody()->getContents(), true);
        } catch (ServerException $e) {
            error_log($e->getMessage());
        }
    }
}

// Runtime
date_default_timezone_set('Europe/London');

// Start
$api = new JsonAPI();

// Call API to get all data
$api->getAllDataFromAPI();

// Save to HTML
$api->saveDataToHTML('events.html');
