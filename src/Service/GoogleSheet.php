<?php

declare(strict_types=1);

namespace App\Service;

use Google\Client;
use Google\Service\Sheets\ClearValuesRequest;

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
        $response = $service->spreadsheets->get($this->spreadsheetId);

        return $response->getSheets();
    }

    public function getValues(string $sheetName): ?array
    {
        $sheet = $this->getSheet($sheetName);
        $range = $sheet->getProperties()->getTitle() . self::DEFAULT_RANGE;
        $service = new \Google_Service_Sheets($this->client);
        $response = $service->spreadsheets_values->get($this->spreadsheetId, $range);

        return $response->getValues();
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
        if ($existingValues) {
            $service = new \Google_Service_Sheets($this->client);
            $service->spreadsheets_values->clear($this->spreadsheetId, $range, new ClearValuesRequest());
            $this->appendValues($sheetName, $values);
            $this->appendValues($sheetName, $existingValues);
        } else {
            $this->appendValues($sheetName, $values);
        }
    }

    public function appendValues(string $sheetName, array $values): void
    {
        $sheet = $this->getSheet($sheetName);
        $range = $sheet->getProperties()->getTitle() . self::DEFAULT_RANGE;
        $service = new \Google_Service_Sheets($this->client);
        $valueRange = new \Google_Service_Sheets_ValueRange();
        $valueRange->setValues($values);
        $params = [
            'valueInputOption' => 'USER_ENTERED',
        ];
        $service->spreadsheets_values->append($this->spreadsheetId, $range, $valueRange, $params);
        sleep(1);
    }
}
