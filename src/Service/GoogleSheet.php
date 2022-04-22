<?php

declare(strict_types=1);

namespace App\Service;

use Google\Client;
use Google\Service\Sheets\AppendCellsRequest;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\CellFormat;
use Google\Service\Sheets\CellData;
use Google\Service\Sheets\ColorStyle;
use Google\Service\Sheets\Color;
use Google\Service\Sheets\RowData;
use Google\Service\Sheets\ValueRange;
use Google\Service\Sheets\ExtendedValue;

class GoogleSheet
{
    private const DEFAULT_RANGE = '!A1:Z1';

    private array $sheetsIdMapping;
    private string $builderSheetId;
    private string $degenSheetId;
    private string $farmerSheetId;
    private string $shitCoinerSheetId;
    private string $spreadsheetId;
    private string $traderSheetId;

    private Client $client;

    public function __construct(
        string $spreadsheetId,
        string $shitCoinerSheetId,
        string $degenSheetId,
        string $traderSheetId,
        string $farmerSheetId,
        string $builderSheetId
    ) {
        $this->spreadsheetId = $spreadsheetId;
        $this->client = new Client();
        $this->client->setApplicationName('Google Sheets API PHP Quickstart');
        $this->client->setScopes(\Google_Service_Sheets::SPREADSHEETS);
        $this->client->setAuthConfig(__DIR__ . '/../../credentials.json');
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');
        $this->shitCoinerSheetId = $shitCoinerSheetId;
        $this->degenSheetId = $degenSheetId;
        $this->traderSheetId = $traderSheetId;
        $this->farmerSheetId = $farmerSheetId;
        $this->builderSheetId = $builderSheetId;
        $this->sheetsIdMapping = [
            'Shitcoiner' => $this->shitCoinerSheetId,
            'Degen'      => $this->degenSheetId,
            'Trader'     => $this->traderSheetId,
            'Farmer'     => $this->farmerSheetId,
            'Builder'    => $this->builderSheetId,
        ];
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

    public function appendValues(string $sheetName, array $values): void
    {
        $valueRange = new \Google_Service_Sheets_ValueRange();
        $valueRange->setValues($values);
        $service = new \Google_Service_Sheets($this->client);
        $range = $sheetName . self::DEFAULT_RANGE;
        $params = [
            'valueInputOption' => 'USER_ENTERED',
        ];

        $this->retry(
            function() use ($service, $range, $valueRange, $params) {
                $service->spreadsheets_values->append($this->spreadsheetId, $range, $valueRange, $params);
            }
        );
    }

    public function appendWalletValues(bool $isNew, string $walletLabel, array $values, ?array $colors = null): void
    {
        $service = new \Google_Service_Sheets($this->client);

        $batchUpdateRequest = $this->getBatchUpdateSpreadsheetRequest($isNew, $walletLabel, $values, $colors);
        $this->retry(
            function() use ($service, $batchUpdateRequest) {
                $service->spreadsheets->batchUpdate($this->spreadsheetId, $batchUpdateRequest);
            }
        );
    }

    private function getBatchUpdateSpreadsheetRequest(bool $isNew, string $walletLabel, array $values, ?array $colors = null): BatchUpdateSpreadsheetRequest
    {
        $batchUpdateRequest = new BatchUpdateSpreadsheetRequest();
        $appendCellsRequest = new AppendCellsRequest();
        $appendCellsRequest->setSheetId($isNew ? 0 : $this->sheetsIdMapping[$walletLabel]);
        $appendCellsRequest->setFields('*');
        $request = [
            'appendCells' => $appendCellsRequest
        ];

        $cells = [];
        $cellFormat = new CellFormat();
        $cellFormat->setHorizontalAlignment('CENTER');

        if ($colors) {
            $colorStyle = new ColorStyle();
            $color = new Color();
            $color->setRed($colors[0]);
            $color->setBlue($colors[1]);
            $color->setGreen($colors[2]);
            $colorStyle->setRgbColor($color);
            $cellFormat->setBackgroundColorStyle($colorStyle);
        }

        foreach ($values[0] as $cell) {
            $cellData = new CellData();
            $cellData->setUserEnteredFormat($cellFormat);

            $extendedValue = new ExtendedValue();
            if (false !== strpos($cell, 'http')) {
                $url = sprintf('=HYPERLINK("%s"; "%s")', $cell, $cell);
                $extendedValue->setFormulaValue($url);

            } else {
                $extendedValue->setStringValue($cell);
            }


            $cellData->setUserEnteredValue($extendedValue);

            $cells[] = $cellData;
        }
        $row = new RowData();
        $row->setValues($cells);
        $appendCellsRequest->setRows($row);
        $batchUpdateRequest->setRequests($request);

        return $batchUpdateRequest;
    }

    private function retry(callable $callable, $maxRetries = 15, $initialWait = 5.0, $exponent = 2)
    {
        try {
            return call_user_func($callable);
        } catch (\Throwable $e) {

            if ($maxRetries > 0) {
                $sleep = $initialWait * 1E6;

                usleep((int)$sleep);
                $initialWait+=1;

                return $this->retry($callable, $maxRetries - 1, $initialWait * $exponent, $exponent);
            }

            // max retries reached
            throw $e;
        }
    }
}
