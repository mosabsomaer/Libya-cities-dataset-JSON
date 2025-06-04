<?php
declare(strict_types=1);

namespace LIBYACITIES;

class Fetcher {
    const OVERPASS_API_URL = 'https://overpass-api.de/api/interpreter';
    const INPUT_FILE = __DIR__ . '/input.json';
    const CITIES_FILE = __DIR__ . '/libya/cities.json';
    const TOWNS_FILE = __DIR__ . '/libya/towns.json';
    const VILLAGES_FILE = __DIR__ . '/libya/villages.json';
    const HAMLETS_FILE = __DIR__ . '/libya/hamlets.json';
    const ALL_FILE = __DIR__ . '/libya/all.json';

    const PLACE_TYPES = [
        'city' => self::CITIES_FILE,
        'town' => self::TOWNS_FILE,
        'village' => self::VILLAGES_FILE,
        'hamlet' => self::HAMLETS_FILE
    ];

    function fetchFromOverpass(): bool {
        $query = '[out:json][timeout:25];
            area(3600192758)->.libya;
            (
            node["place"="city"](area.libya);
            node["place"="town"](area.libya);
            node["place"="village"](area.libya);
            node["place"="hamlet"](area.libya);
            );
            out body;';
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'timeout' => 30,
                'content' => 'data=' . urlencode($query)
            ]
        ]);

        $response = @file_get_contents(self::OVERPASS_API_URL, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            throw new \RuntimeException('Failed to fetch data from Overpass API: ' . ($error['message'] ?? 'Unknown error'));
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response from Overpass API');
        }

        return file_put_contents(self::INPUT_FILE, $response) !== false;
    }

    function processData(): void {
        // Load input data
        $inputData = json_decode(file_get_contents(self::INPUT_FILE), true);
        if (!isset($inputData['elements'])) {
            throw new \RuntimeException('Invalid input data structure');
        }

        // Load existing data for all place types
        $existingData = [];
        foreach (self::PLACE_TYPES as $placeType => $filePath) {
            $existingData[$placeType] = file_exists($filePath) ? 
                json_decode(file_get_contents($filePath), true) : [];
        }

        // Group elements by place type
        $groupedElements = array_fill_keys(array_keys(self::PLACE_TYPES), []);
        
        foreach ($inputData['elements'] as $element) {
            // Validate element
            if (!isset($element['type'], $element['lat'], $element['lon'], $element['tags']['place'], $element['tags']['name']) ||
                $element['type'] !== 'node' ||
                !array_key_exists($element['tags']['place'], self::PLACE_TYPES)) {
                continue;
            }

            // Extract multilingual names
            $names = [];
            $defaultName = $element['tags']['name'] ?? '';
            
            foreach ($element['tags'] as $key => $value) {
                if (strpos($key, 'name:') === 0 && $value) {
                    $language = substr($key, 5);
                    $names[$language] = $value;
                }
            }
            
            if (empty($names) && $defaultName) {
                $language = preg_match('/\p{Arabic}/u', $defaultName) ? 'ar' : 'en';
                $names[$language] = $defaultName;
            }

            if (empty($names)) {
                continue;
            }

            // Create processed element
            $processedElement = [
                'name' => [$names],
                'latitude' => (float) $element['lat'],
                'longitude' => (float) $element['lon'],
                'type' => $element['tags']['place']
            ];

            // Group by place type
            $groupedElements[$element['tags']['place']][] = $processedElement;
        }

        // Process each place type
        $allPlaces = [];
        foreach (self::PLACE_TYPES as $placeType => $filePath) {
            $result = $this->mergeData($existingData[$placeType], $groupedElements[$placeType]);
            
            // Remove type field for individual files
            $dataForFile = array_map(function($item) {
                $itemCopy = $item;
                unset($itemCopy['type']);
                return $itemCopy;
            }, $result['data']);
            
            file_put_contents($filePath, json_encode($dataForFile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // Add type field to all entries for all.json and combine
            $dataWithType = array_map(function($item) use ($placeType) {
                $item['type'] = $placeType;
                return $item;
            }, $result['data']);
            
            $allPlaces = array_merge($allPlaces, $dataWithType);
            
            // Display result
            $placeTypeName = ucfirst($placeType) . ($placeType === 'city' ? 'ies' : 's');
            if ($result['new'] > 0 || $result['updated'] > 0) {
                echo "{$placeTypeName}: {$result['new']} new, {$result['updated']} updated, {$result['total']} total\n";
            } else {
                echo "{$placeTypeName}: No updates needed ({$result['total']} total)\n";
            }
        }

        // Save all places combined
        file_put_contents(self::ALL_FILE, json_encode($allPlaces, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "All places combined: " . count($allPlaces) . " total entries saved\n";
    }

    function mergeData($existingData, $newElements) {
        $merged = $existingData;
        $newCount = 0;
        $updatedCount = 0;
        
        // Create index of existing data by primary name for fast lookup
        $existingByName = [];
        foreach ($existingData as $i => $item) {
            if (isset($item['name'][0])) {
                $primaryName = $item['name'][0]['ar'] ?? $item['name'][0]['en'] ?? array_values($item['name'][0])[0] ?? '';
                $existingByName[$primaryName] = $i;
            }
        }

        // Get next ID
        $nextId = 0;
        foreach ($existingData as $item) {
            if (isset($item['id']) && $item['id'] > $nextId) {
                $nextId = $item['id'];
            }
        }
        $nextId++;

        foreach ($newElements as $newElement) {
            $primaryName = $newElement['name'][0]['ar'] ?? $newElement['name'][0]['en'] ?? array_values($newElement['name'][0])[0] ?? '';
            $existingIndex = $existingByName[$primaryName] ?? null;

            if ($existingIndex !== null) {
                // Check if update is needed
                $existing = $merged[$existingIndex];
                $existingNames = $existing['name'][0] ?? [];
                $hasNewNames = false;
                $needsCoordinates = empty($existing['latitude']) || empty($existing['longitude']);
                
                // Check for new language names
                foreach ($newElement['name'][0] as $lang => $name) {
                    if (!isset($existingNames[$lang])) {
                        $hasNewNames = true;
                        break;
                    }
                }
                
                if ($hasNewNames || $needsCoordinates) {
                    // Update existing element - merge names and update coordinates if missing
                    $mergedNames = $existingNames;
                    
                    foreach ($newElement['name'][0] as $lang => $name) {
                        if (!isset($mergedNames[$lang]) || $needsCoordinates) {
                            $mergedNames[$lang] = $name;
                        }
                    }

                    $updatedElement = [
                        'id' => $existing['id'] ?? $nextId++,
                        'name' => [$mergedNames],
                        'latitude' => !empty($existing['latitude']) ? $existing['latitude'] : $newElement['latitude'],
                        'longitude' => !empty($existing['longitude']) ? $existing['longitude'] : $newElement['longitude']
                    ];
                    
                    // Add type if it exists in new element
                    if (isset($newElement['type'])) {
                        $updatedElement['type'] = $newElement['type'];
                    }
                    
                    $merged[$existingIndex] = $updatedElement;
                    $updatedCount++;
                }
            } else {
                // Add new element
                $newElement['id'] = $nextId++;
                $merged[] = $newElement;
                $newCount++;
            }
        }

        return [
            'data' => $merged,
            'new' => $newCount,
            'updated' => $updatedCount,
            'total' => count($merged)
        ];
    }
}