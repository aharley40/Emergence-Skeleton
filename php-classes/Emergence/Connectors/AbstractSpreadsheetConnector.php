<?php

namespace Emergence\Connectors;

use ActiveRecord;
use SpreadsheetReader;
use Psr\Log\LogLevel;

class AbstractSpreadsheetConnector extends \Emergence\Connectors\AbstractConnector
{
    public static $logRowColumnCount = 3;

    // protected methods
    protected static function _requireColumns($noun, SpreadsheetReader $spreadsheet, array $requiredColumns, array $columnsMap = null)
    {
        $columns = $spreadsheet->getColumnNames();

        if ($columnsMap) {
            $mappedColumns = array();
            foreach ($columns AS $columnName) {
                $mappedColumns[] = array_key_exists($columnName, $columnsMap) ? $columnsMap[$columnName] : $columnName;
            }
            $columns = $mappedColumns;
        }

        $missingColumns = array_diff($requiredColumns, $columns);

        if (count($missingColumns)) {
            throw new \Exception(
                $noun.' spreadsheet is missing required column' . (count($missingColumns) != 1 ? 's' : '') . ': '
                .join(',', $missingColumns)
            );
        }
    }

    protected static function _readRow(array $row, array $columnsMap)
    {
        $output = array();

        foreach ($columnsMap AS $externalKey => $internalKey) {
            if (array_key_exists($externalKey, $row)) {
                $output[$internalKey] = $row[$externalKey];
                unset($row[$externalKey]);
            }
        }

        $output['_rest'] = $row;

        return $output;
    }

    protected static function _logRow(Job $Job, $noun, $rowNumber, array $row)
    {
        $nonEmptyColumns = array_filter($row);
        unset($nonEmptyColumns['_rest']);

        $summaryColumns = array_slice($nonEmptyColumns, 0, static::$logRowColumnCount, true);

        return $Job->log(sprintf(
            'Analyzing %s row #%03u: %s',
            $noun,
            $rowNumber,
            http_build_query($summaryColumns) . (count($nonEmptyColumns) > count($summaryColumns) ? '&...' : '')
        ), LogLevel::DEBUG);
    }

    protected static function _validateRecord(Job $Job, ActiveRecord $Record, array &$results)
    {
        // call configurable hook
        if (is_callable(static::$onBeforeValidateRecord)) {
            call_user_func(static::$onBeforeValidateRecord, $Job, $Record, $results);
        }


        // validate and store result
        $isValid = $Record->validate();


        // trace any failed validation in the log and in the results
        if (!$isValid) {
            $firstErrorField = key($Record->validationErrors);
            $error = $Record->validationErrors[$firstErrorField];
            $results['failed']['invalid'][$firstErrorField][is_array($error) ? http_build_query($error) : $error]++;
            $Job->logInvalidRecord($Record);
        }


        // call configurable hook
        if (is_callable(static::$onValidateRecord)) {
            call_user_func(static::$onValidateRecord, $Job, $Record, $results, $isValid);
        }


        return $isValid;
    }

    protected static function _saveRecord(Job $Job, ActiveRecord $Record, $pretend, array &$results, $logOptions = array())
    {
        // call configurable hook
        if (is_callable(static::$onBeforeSaveRecord)) {
            call_user_func(static::$onBeforeSaveRecord, $Job, $Record, $results, $pretend, $logOptions);
        }


        // generate log entry
        $logEntry = $Job->logRecordDelta($Record, $logOptions);

        if ($logEntry['action'] == 'create') {
            $results['created']++;
        } elseif ($logEntry['action'] == 'update') {
            $results['updated']++;
        }


        // save changes
        if (!$pretend) {
            $Record->save();
        }


        // call configurable hook
        if (is_callable(static::$onSaveRecord)) {
            call_user_func(static::$onSaveRecord, $Job, $Record, $results, $pretend, $logOptions);
        }
    }
}