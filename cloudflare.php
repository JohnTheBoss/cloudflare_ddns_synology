#!/usr/bin/php -d open_basedir=/usr/syno/bin/ddns
<?php

if ($argc !== 5) {
    echo 'badparam';
    exit();
}

$email = (string)$argv[1];
$token = (string)$argv[2];
$hostname = (string)$argv[3];
$ip = (string)$argv[4];

try {
    $cfddns = new CloudflareDDNS($email, $token, $hostname, $ip);
    $cfddns->run();
    echo 'good';
} catch (\Exception $ex) {
    echo 'badparam ' . $ex->getMessage();
    exit();
}

class CloudflareDDNS
{
    private static $endpoint = 'https://api.cloudflare.com/client/v4';

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $authToken;

    /**
     * @var array
     */
    private $hostnames;
    /**
     * @var string
     */
    private $ip;

    private $IPType = null;

    /**
     * @var array
     */
    private $zones;

    public function __construct($email, $token, $hostname, $ip)
    {
        $this->email = $this->validateParameter('email', $email);
        $this->authToken = $this->validateParameter('token', $token);
        $this->hostnames[] = $this->validateParameter('hostname', $hostname);
        $this->ip = $this->validateParameter('ip', $ip);

        $this->setIPType($this->ip);
        $this->getZones();
    }

    public function run()
    {
        foreach ($this->hostnames as $hostname) {
            $record = $this->getDNSRecord($hostname);
            $zoneId = $this->zones[$hostname];

            if ($record === null) {
                $this->createRecord($zoneId, $hostname);
            } else {
                $this->updateRecord($record);
            }
        }
    }

    private function createRecord($zoneId, $hostname)
    {
        $data = [
            'type' => $this->IPType,
            'name' => $hostname,
            'content' => $this->ip,
            'ttl' => 1
        ];

        $this->getResult('zones/' . $zoneId . '/dns_records', 'POST', $data);
    }

    private function updateRecord($record)
    {
        if ($record['context'] !== $this->ip) {
            $data = [
                'content' => $this->ip,
            ];

            $this->getResult('zones/' . $record['zone_id'] . '/dns_records/' . $record['id'], 'PATCH', $data);
        }
    }

    private function validateParameter($type, $value)
    {
        $validatorMap = [
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL),
            'token' => filter_var($value, FILTER_VALIDATE_REGEXP, [
                "options" => [
                    "regexp" => '/.{1,}/',
                ],
            ]),
            'hostname' => filter_var($value, FILTER_VALIDATE_DOMAIN),
            'ip' => filter_var($value, FILTER_VALIDATE_IP),
        ];

        if (isset($validatorMap[$type])) {
            $isValid = $validatorMap[$type];

            if ($isValid === false) {
                throw new \Exception('The "' . $type . '" is invalid!');
            }

            return $isValid;
        }

        throw new \Exception('validator type "' . $type . '" does\'t exist.');
    }

    private function setIPType($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $this->IPType = 'AAAA';
        } else {
            $this->IPType = 'A';
        }
    }

    private function getZones()
    {
        $zones = $this->getResult('zones');

        foreach ($zones as $zone) {
            foreach ($this->hostnames as $hostname) {
                if (strpos($hostname, $zone['name']) !== false) {
                    $this->zones[$hostname] = $zone['id'];
                }
                break;
            }
        }
    }

    private function getDNSRecord($hostname)
    {
        $records = $this->getResult('zones/' . $this->zones[$hostname] . '/dns_records?name=' . $hostname);

        if (count($records) === 0) {
            return null;
        }

        return $this->findRecordByType($records, $this->IPType)[0];
    }

    private function findRecordByType($records, $type)
    {
        return array_filter($records, function ($record) use ($type) {
            return $record['type'] === $type;
        });
    }

    private function getResult($path, $method = 'GET', $data = null)
    {
        $result = $this->callApi($path, $method, $data);
        if (!$result['success']) {
            throw new \Exception('The "' . $path . '" quarry is failed. More information: ', json_encode($result['errors']));
        }

        return $result['result'];
    }

    protected function callApi($path, $method = 'GET', $data = null)
    {
        $options = [
            CURLOPT_URL => self::$endpoint . '/' . $path,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->authToken,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_VERBOSE => false,
        ];

        switch ($method) {
            case 'GET':
                $options[CURLOPT_HTTPGET] = true;
                break;

            case 'POST':
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_HTTPGET] = false;
                if ($data !== null) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;

            case 'PUT':
            case 'PATCH':
                $options[CURLOPT_POST] = false;
                $options[CURLOPT_HTTPGET] = false;
                $options[CURLOPT_CUSTOMREQUEST] = $method;
                if ($data !== null) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;
        }

        $request = curl_init();
        curl_setopt_array($request, $options);
        $response = curl_exec($request);
        curl_close($request);

        return json_decode($response, true);
    }
}