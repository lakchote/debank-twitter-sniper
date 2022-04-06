<?php

declare(strict_types=1);

namespace App\Service;

use Google\Client;
use Google\Service\Sheets\ClearValuesRequest;
use Google\Service\Sheets\ValueRange;

class GoogleSheet
{
    private const DEFAULT_RANGE = '!A2:ZZ';
    private string $spreadsheetId;
    private Client $client;

    public function __construct(string $spreadsheetId)
    {
        $this->spreadsheetId = $spreadsheetId;
        $this->client = new Client();
        $this->client->setApplicationName('Google Sheets API PHP Quickstart');
        $this->client->setScopes(\Google_Service_Sheets::SPREADSHEETS);
        $this->client->setAuthConfig(__DIR__ . '/../../credentials.json');
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');
    }

    public function getSheet(string $sheetName): \Google_Service_Sheets_Sheet
    {
        $sheets = $this->getSheets();
        foreach ($sheets as $sheet) {
            if ($sheet->getProperties()->getTitle() === $sheetName) {
                $this->sheet = $sheet;

                return $sheet;
            }
        }

        throw new \Exception('Sheet not found');
    }

    public function getSheets(): array
    {
        $service = new \Google_Service_Sheets($this->client);

        return $service->spreadsheets->get($this->spreadsheetId)->getSheets();
    }

    public function getValues(string $sheetName): ValueRange
    {
        $range = $sheetName . self::DEFAULT_RANGE;
        $service = new \Google_Service_Sheets($this->client);

        return $this->retry(
            function() use ($service, $range) {
                return $service->spreadsheets_values->get($this->spreadsheetId, $range);
            }
        );
    }

    public function getValue(string $sheetName, string $cell): string
    {
        $values = $this->getValues($sheetName);
        foreach ($values as $value) {
            if ($value[0] === $cell) {
                return $value[1];
            }
        }

        throw new \Exception('Cell not found');
    }

    public function prependValues(string $sheetName, array $values): void
    {
        $range = $sheetName . self::DEFAULT_RANGE;
        $existingValues = $this->getValues($sheetName);
        $valueRange = new \Google_Service_Sheets_ValueRange();
        $valueRange->setValues($values);
        $valueRange->setRange($range);
        if ($existingValues) {
            $service = new \Google_Service_Sheets($this->client);
            $service->spreadsheets_values->clear($this->spreadsheetId, $range, new ClearValuesRequest());
            $this->appendValues($valueRange);
            $this->appendValues($existingValues);
        } else {
            $this->appendValues($valueRange);
        }
    }

    public function appendValues(ValueRange $valueRange): void
    {
        $range = $valueRange->getRange();
        $service = new \Google_Service_Sheets($this->client);

        $params = [
            'valueInputOption' => 'USER_ENTERED',
        ];

        $this->retry(
            function() use ($service, $range, $valueRange, $params) {
                $service->spreadsheets_values->append($this->spreadsheetId, $range, $valueRange, $params);
            }
        );
    }

    private function retry(callable $callable, $maxRetries = 15, $initialWait = 5.0, $exponent = 2)
    {
        try {
            return call_user_func($callable);
        } catch (\Exception $e) {

            if ($maxRetries > 0) {
                $sleep = $initialWait * 1E6;

                usleep((int)$sleep);

                return $this->retry($callable, $maxRetries - 1, $initialWait * $exponent, $exponent);
            }

            // max retries reached
            throw $e;
        }
    }
}
