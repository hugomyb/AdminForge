<?php

namespace App\Services;

class DataPreviewService
{
    /**
     * Formate une valeur pour l'affichage selon son type
     */
    public function formatValue($value, string $columnType, int $maxLength = 100): array
    {
        if ($value === null) {
            return [
                'display' => '<span class="text-gray-400 italic">NULL</span>',
                'raw' => null,
                'type' => 'null'
            ];
        }

        $columnType = strtolower($columnType);
        
        // JSON
        if ($this->isJsonType($columnType) || $this->isValidJson($value)) {
            return $this->formatJson($value, $maxLength);
        }
        
        // Date/DateTime
        if ($this->isDateType($columnType)) {
            return $this->formatDate($value);
        }
        
        // Boolean
        if ($this->isBooleanType($columnType)) {
            return $this->formatBoolean($value);
        }
        
        // Enum
        if ($this->isEnumType($columnType)) {
            return $this->formatEnum($value);
        }
        
        // Text/Blob (long content)
        if ($this->isTextType($columnType)) {
            return $this->formatText($value, $maxLength);
        }
        
        // Numeric
        if ($this->isNumericType($columnType)) {
            return $this->formatNumeric($value);
        }
        
        // Default string
        return $this->formatString($value, $maxLength);
    }

    protected function isJsonType(string $columnType): bool
    {
        return str_contains($columnType, 'json');
    }

    protected function isDateType(string $columnType): bool
    {
        return in_array($columnType, ['date', 'datetime', 'timestamp', 'time']);
    }

    protected function isBooleanType(string $columnType): bool
    {
        return in_array($columnType, ['boolean', 'bool', 'tinyint(1)']);
    }

    protected function isEnumType(string $columnType): bool
    {
        return str_starts_with($columnType, 'enum');
    }

    protected function isTextType(string $columnType): bool
    {
        return in_array($columnType, ['text', 'mediumtext', 'longtext', 'blob', 'mediumblob', 'longblob']);
    }

    protected function isNumericType(string $columnType): bool
    {
        return preg_match('/^(int|integer|bigint|smallint|tinyint|decimal|numeric|float|double|real)/', $columnType);
    }

    protected function isValidJson($value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    protected function formatJson($value, int $maxLength): array
    {
        try {
            $decoded = json_decode($value, true);
            $formatted = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if (strlen($formatted) > $maxLength) {
                $truncated = substr($formatted, 0, $maxLength) . '...';
                return [
                    'display' => '<pre class="text-xs bg-gray-100 dark:bg-gray-700 p-2 rounded overflow-hidden">' . htmlspecialchars($truncated) . '</pre>',
                    'raw' => $value,
                    'type' => 'json',
                    'preview' => $truncated,
                    'full' => $formatted
                ];
            }
            
            return [
                'display' => '<pre class="text-xs bg-gray-100 dark:bg-gray-700 p-2 rounded">' . htmlspecialchars($formatted) . '</pre>',
                'raw' => $value,
                'type' => 'json',
                'full' => $formatted
            ];
        } catch (\Exception $e) {
            return $this->formatString($value, $maxLength);
        }
    }

    protected function formatDate($value): array
    {
        try {
            $date = new \DateTime($value);
            $formatted = $date->format('d/m/Y H:i:s');
            $relative = $this->getRelativeTime($date);
            
            return [
                'display' => '<span class="text-blue-600 dark:text-blue-400" title="' . $relative . '">' . htmlspecialchars($formatted) . '</span>',
                'raw' => $value,
                'type' => 'date',
                'formatted' => $formatted,
                'relative' => $relative
            ];
        } catch (\Exception $e) {
            return $this->formatString($value, 50);
        }
    }

    protected function formatBoolean($value): array
    {
        $boolValue = (bool) $value;
        $display = $boolValue 
            ? '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Vrai</span>'
            : '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Faux</span>';
        
        return [
            'display' => $display,
            'raw' => $value,
            'type' => 'boolean',
            'boolean' => $boolValue
        ];
    }

    protected function formatEnum($value): array
    {
        return [
            'display' => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">' . htmlspecialchars($value) . '</span>',
            'raw' => $value,
            'type' => 'enum'
        ];
    }

    protected function formatText($value, int $maxLength): array
    {
        $length = strlen($value);
        
        if ($length > $maxLength) {
            $truncated = substr($value, 0, $maxLength) . '...';
            return [
                'display' => '<span class="text-gray-700 dark:text-gray-300" title="' . $length . ' caractères">' . htmlspecialchars($truncated) . '</span>',
                'raw' => $value,
                'type' => 'text',
                'preview' => $truncated,
                'full' => $value,
                'length' => $length
            ];
        }
        
        return [
            'display' => '<span class="text-gray-700 dark:text-gray-300">' . htmlspecialchars($value) . '</span>',
            'raw' => $value,
            'type' => 'text',
            'length' => $length
        ];
    }

    protected function formatNumeric($value): array
    {
        if (is_numeric($value)) {
            $formatted = number_format((float)$value, 0, ',', ' ');
            return [
                'display' => '<span class="text-green-600 dark:text-green-400 font-mono">' . htmlspecialchars($formatted) . '</span>',
                'raw' => $value,
                'type' => 'numeric',
                'formatted' => $formatted
            ];
        }
        
        return $this->formatString($value, 50);
    }

    protected function formatString($value, int $maxLength): array
    {
        $stringValue = (string) $value;
        $length = strlen($stringValue);
        
        if ($length > $maxLength) {
            $truncated = substr($stringValue, 0, $maxLength) . '...';
            return [
                'display' => htmlspecialchars($truncated),
                'raw' => $value,
                'type' => 'string',
                'preview' => $truncated,
                'full' => $stringValue,
                'length' => $length
            ];
        }
        
        return [
            'display' => htmlspecialchars($stringValue),
            'raw' => $value,
            'type' => 'string',
            'length' => $length
        ];
    }

    protected function getRelativeTime(\DateTime $date): string
    {
        $now = new \DateTime();
        $diff = $now->diff($date);
        
        if ($diff->days > 365) {
            return $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
        } elseif ($diff->days > 30) {
            return $diff->m . ' mois';
        } elseif ($diff->days > 0) {
            return $diff->days . ' jour' . ($diff->days > 1 ? 's' : '');
        } elseif ($diff->h > 0) {
            return $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        } else {
            return 'À l\'instant';
        }
    }

    /**
     * Génère un lien vers un enregistrement FK si possible
     */
    public function generateForeignKeyLink($value, string $referencedTable, string $referencedColumn, string $currentDatabase): ?string
    {
        if ($value === null) {
            return null;
        }
        
        return route('filament.admin.pages.table-data') . 
               '?database=' . urlencode($currentDatabase) . 
               '&table=' . urlencode($referencedTable) . 
               '&filter=' . urlencode($referencedColumn . '=' . $value);
    }
}
