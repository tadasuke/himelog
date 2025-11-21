<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait LogsMethodExecution
{
    /**
     * メソッド開始時のログを出力
     */
    protected function logMethodStart(string $methodName, array $args = [], ?string $file = null, ?int $line = null): void
    {
        $file = $file ?? $this->getCallerFile();
        $line = $line ?? $this->getCallerLine();
        
        $className = get_class($this);
        $argsForLog = $this->sanitizeArguments($args);
        
        Log::info("Method started: {$className}::{$methodName}", [
            'file' => $file,
            'line' => $line,
            'method' => $methodName,
            'class' => $className,
            'arguments' => $argsForLog,
        ]);
    }

    /**
     * メソッド終了時のログを出力
     */
    protected function logMethodEnd(string $methodName, $returnValue = null, ?string $file = null, ?int $line = null): void
    {
        $file = $file ?? $this->getCallerFile();
        $line = $line ?? $this->getCallerLine();
        
        $className = get_class($this);
        $returnValueForLog = $this->sanitizeReturnValue($returnValue);
        
        Log::info("Method ended: {$className}::{$methodName}", [
            'file' => $file,
            'line' => $line,
            'method' => $methodName,
            'class' => $className,
            'return_value' => $returnValueForLog,
            'return_type' => gettype($returnValue),
        ]);
    }

    /**
     * 呼び出し元のファイル名を取得
     */
    private function getCallerFile(): string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        // logMethodStart/logMethodEnd -> 実際のメソッド -> 呼び出し元
        // インデックス2が実際のメソッド、3が呼び出し元
        if (isset($backtrace[2]['file'])) {
            return $backtrace[2]['file'];
        }
        if (isset($backtrace[1]['file'])) {
            return $backtrace[1]['file'];
        }
        return __FILE__;
    }

    /**
     * 呼び出し元の行番号を取得
     */
    private function getCallerLine(): int
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        // logMethodStart/logMethodEnd -> 実際のメソッド -> 呼び出し元
        // インデックス2が実際のメソッド、3が呼び出し元
        if (isset($backtrace[2]['line'])) {
            return $backtrace[2]['line'];
        }
        if (isset($backtrace[1]['line'])) {
            return $backtrace[1]['line'];
        }
        return __LINE__;
    }

    /**
     * 引数をログ用にサニタイズ（機密情報を除外）
     */
    private function sanitizeArguments(array $args): array
    {
        $sanitized = [];
        foreach ($args as $key => $value) {
            if (is_object($value)) {
                if ($value instanceof \Illuminate\Http\Request) {
                    // Requestオブジェクトの場合は、機密情報を除外
                    $sanitized[$key] = [
                        'type' => 'Request',
                        'method' => $value->method(),
                        'path' => $value->path(),
                        'query' => $value->query(),
                        'input' => $this->sanitizeRequestInput($value->all()),
                    ];
                } else {
                    $sanitized[$key] = [
                        'type' => get_class($value),
                        'string' => method_exists($value, '__toString') ? (string)$value : '[object]',
                    ];
                }
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value) && strlen($value) > 1000) {
                // 長い文字列は切り詰める
                $sanitized[$key] = substr($value, 0, 1000) . '... (truncated)';
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * 戻り値をログ用にサニタイズ
     */
    private function sanitizeReturnValue($value)
    {
        if (is_object($value)) {
            if ($value instanceof \Illuminate\Http\JsonResponse) {
                // JsonResponseの場合は、データを取得
                $data = $value->getData(true);
                return [
                    'type' => 'JsonResponse',
                    'status_code' => $value->getStatusCode(),
                    'data' => $this->sanitizeArray(is_array($data) ? $data : []),
                ];
            } elseif (method_exists($value, 'toArray')) {
                return [
                    'type' => get_class($value),
                    'data' => $this->sanitizeArray($value->toArray()),
                ];
            } else {
                return [
                    'type' => get_class($value),
                    'string' => method_exists($value, '__toString') ? (string)$value : '[object]',
                ];
            }
        } elseif (is_array($value)) {
            return $this->sanitizeArray($value);
        } elseif (is_string($value) && strlen($value) > 1000) {
            return substr($value, 0, 1000) . '... (truncated)';
        } else {
            return $value;
        }
    }

    /**
     * 配列をサニタイズ（機密情報を除外）
     */
    private function sanitizeArray(array $array): array
    {
        $sanitized = [];
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'access_token', 'refresh_token'];
        
        foreach ($array as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveKeys) || str_contains($lowerKey, 'password') || str_contains($lowerKey, 'token')) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } elseif (is_object($value)) {
                $sanitized[$key] = [
                    'type' => get_class($value),
                    'string' => method_exists($value, '__toString') ? (string)$value : '[object]',
                ];
            } elseif (is_string($value) && strlen($value) > 500) {
                $sanitized[$key] = substr($value, 0, 500) . '... (truncated)';
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Requestの入力データをサニタイズ
     */
    private function sanitizeRequestInput(array $input): array
    {
        return $this->sanitizeArray($input);
    }
}

