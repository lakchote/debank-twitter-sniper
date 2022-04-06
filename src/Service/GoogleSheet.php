<?php

declare(strict_types=1);

namespace App\Service;

use Google\Client;
use Google\Service\Sheets\ClearValuesRequest;
use Google\Service\Sheets\ValueRange;

class GoogleSheet
{
    private const EXPECTED_ERRORS = 'Google_Service_Exception';
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
        $sheet = $this->getSheet($sheetName);
        $range = $sheet->getProperties()->getTitle() . self::DEFAULT_RANGE;
        $service = new \Google_Service_Sheets($this->client);

        return $this->retry(
            function() use ($service, $range) {
                return $service->spreadsheets_values->get($this->spreadsheetId, $range);
            },
            self::EXPECTED_ERRORS
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
        $sheet = $this->getSheet($sheetName);
        $range = $sheet->getProperties()->getTitle() . self::DEFAULT_RANGE;
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
            },
            self::EXPECTED_ERRORS
        );
    }

    private function retry(callable $callable, $expectedErrors, $maxRetries = 5, $initialWait = 1.0, $exponent = 2)
    {
        if (!is_array($expectedErrors)) {
            $expectedErrors = [$expectedErrors];
        }

        try {
            return call_user_func($callable);
        } catch (\Exception $e) {
            $errors = class_parents($e);
            $errors[] = get_class($e);

            if (!array_intersect($errors, $expectedErrors)) {
                throw $e;
            }

            if ($maxRetries > 0) {
                usleep($initialWait * 1E6);

                return $this->retry($callable, $expectedErrors, $maxRetries - 1, $initialWait * $exponent, $exponent);
            }

            // max retries reached
            throw $e;
        }
    }
}
