<?php

namespace SulimanBenhalim\LaravelSuperJson;

use SulimanBenhalim\LaravelSuperJson\Exceptions\SuperJsonException;
use SulimanBenhalim\LaravelSuperJson\Transformers\TypeTransformer;

class SuperJson
{
    /** @var TypeTransformer[] Registered type transformers */
    protected array $transformers = [];

    /** @var array Serialization options */
    protected array $options;

    /** @var array Security and configuration settings */
    protected array $config;

    /** @var array Metadata for transformed values */
    protected array $meta = [];

    /** @var string Current transformation path for error context */
    protected string $currentPath = '';

    /** @var int Current recursion depth for circular reference detection */
    protected int $currentDepth = 0;

    /** @var array Object IDs being processed to detect circular references */
    protected array $processedObjects = [];

    public function __construct(array $customTransformers = [], array $options = [])
    {
        $this->options = array_merge([
            'preserve_zero_fraction' => true,
            'unescaped_unicode' => true,
            'throw_on_error' => true,
        ], $options);

        // Load configuration - merge with defaults
        $this->config = array_merge([
            'security' => [
                'allow_class_restoration' => false,
                'allowed_classes' => [],
                'max_array_size' => 1000,
                'max_depth' => 10,
                'sanitize_logged_content' => true,
                'validate_input' => true,
            ],
        ], config('superjson', []));

        $this->registerDefaultTransformers();

        foreach ($customTransformers as $transformer) {
            if (is_string($transformer)) {
                $transformer = new $transformer;
            }
            $this->registerTransformer($transformer);
        }
    }

    /**
     * Serialize data to SuperJSON format with security validation
     *
     * @param  mixed  $data  Data to serialize
     * @return array SuperJSON formatted array with 'json' and optional 'meta' keys
     *
     * @throws SuperJsonException If serialization fails or security limits exceeded
     */
    public function serialize($data): array
    {
        $this->meta = [];
        $this->currentPath = '';
        $this->currentDepth = 0;
        $this->processedObjects = [];

        try {
            $json = $this->transform($data);

            $result = ['json' => $json];

            if (! empty($this->meta)) {
                $result['meta'] = ['values' => $this->meta];
            }

            return $result;
        } catch (\Throwable $e) {
            throw SuperJsonException::serializationFailed(
                'Serialization failed: '.$e->getMessage(),
                ['original_error' => $e->getMessage()]
            );
        }
    }

    /**
     * Deserialize SuperJSON format to PHP data with security validation
     *
     * @param  mixed  $input  SuperJSON formatted data or JSON string
     * @return mixed Deserialized PHP data
     *
     * @throws SuperJsonException If deserialization fails or security violations detected
     */
    public function deserialize($input): mixed
    {
        // Input validation
        $validateInput = $this->config['security']['validate_input'] ?? true;
        if ($validateInput) {
            $this->validateInput($input);
        }

        if (is_string($input)) {
            $input = json_decode($input, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw SuperJsonException::deserializationFailed('Invalid JSON: '.json_last_error_msg());
            }
        }

        if (! is_array($input) || ! isset($input['json'])) {
            // Plain JSON fallback
            return $input;
        }

        $meta = $input['meta']['values'] ?? [];

        try {
            return $this->restore($input['json'], $meta);
        } catch (\Throwable $e) {
            throw SuperJsonException::deserializationFailed(
                'Deserialization failed: '.$e->getMessage(),
                ['original_error' => $e->getMessage()]
            );
        }
    }

    /**
     * Transform PHP data for SuperJSON serialization with security checks
     *
     * @param  mixed  $value  Value to transform
     * @param  string  $path  Current path for error context
     * @return mixed Transformed value
     *
     * @throws SuperJsonException If security limits exceeded or circular references detected
     */
    protected function transform($value, string $path = ''): mixed
    {
        // Check recursion depth
        $this->currentDepth++;
        $maxDepth = $this->config['security']['max_depth'] ?? 10;
        if ($this->currentDepth > $maxDepth) {
            throw SuperJsonException::serializationFailed('Maximum recursion depth exceeded', [
                'max_depth' => $maxDepth,
                'current_depth' => $this->currentDepth,
            ]);
        }

        // Check for circular references in objects
        if (is_object($value)) {
            $objectId = spl_object_id($value);
            if (in_array($objectId, $this->processedObjects, true)) {
                throw SuperJsonException::serializationFailed('Circular reference detected', [
                    'class' => get_class($value),
                    'path' => $path,
                ]);
            }
            $this->processedObjects[] = $objectId;
        }

        $previousPath = $this->currentPath;
        $this->currentPath = $path;

        try {
            foreach ($this->transformers as $transformer) {
                if ($transformer->canTransform($value)) {
                    $result = $transformer->transform($value);
                    $this->meta[$path] = [$transformer->getType()];

                    return $result;
                }
            }

            if (is_array($value)) {
                // Check array size limit
                $maxArraySize = $this->config['security']['max_array_size'] ?? 1000;
                if (count($value) > $maxArraySize) {
                    throw SuperJsonException::serializationFailed('Array size exceeds maximum limit', [
                        'max_size' => $maxArraySize,
                        'actual_size' => count($value),
                    ]);
                }

                $result = [];
                foreach ($value as $key => $item) {
                    $itemPath = $path ? "$path.$key" : (string) $key;
                    $result[$key] = $this->transform($item, $itemPath);
                }

                return $result;
            }

            if (is_object($value) && ! ($value instanceof \stdClass)) {
                // Handle generic objects
                $className = get_class($value);
                $this->meta[$path] = ['class:'.$className];

                $result = [];
                foreach (get_object_vars($value) as $key => $val) {
                    $propPath = $path ? "$path.$key" : $key;
                    $result[$key] = $this->transform($val, $propPath);
                }

                return $result;
            }

            return $value;
        } finally {
            $this->currentPath = $previousPath;
            $this->currentDepth--;

            // Remove from processed objects when done
            if (is_object($value)) {
                $objectId = spl_object_id($value);
                $index = array_search($objectId, $this->processedObjects, true);
                if ($index !== false) {
                    unset($this->processedObjects[$index]);
                }
            }
        }
    }

    /**
     * Restore PHP data from SuperJSON format with security validation
     *
     * @param  mixed  $data  Data to restore
     * @param  array  $meta  Metadata for type information
     * @param  string  $path  Current path for error context
     * @return mixed Restored PHP data
     *
     * @throws SuperJsonException If class restoration is disabled or security violations detected
     */
    protected function restore($data, array $meta, string $path = ''): mixed
    {
        if (isset($meta[$path])) {
            $type = $meta[$path][0];

            foreach ($this->transformers as $transformer) {
                if ($transformer->getType() === $type) {
                    return $transformer->restore($data);
                }
            }

            // Handle custom class restoration - SECURITY CRITICAL
            if (str_starts_with($type, 'class:')) {
                // Check if class restoration is allowed
                $allowClassRestoration = $this->config['security']['allow_class_restoration'] ?? false;
                if (! $allowClassRestoration) {
                    throw new SuperJsonException("Class restoration is disabled for security. Set 'security.allow_class_restoration' to true if needed.");
                }

                $className = substr($type, 6);

                // Validate against allowed classes whitelist
                $allowedClasses = $this->config['security']['allowed_classes'] ?? [];
                if (! empty($allowedClasses) && ! in_array($className, $allowedClasses, true)) {
                    throw new SuperJsonException("Class '$className' is not in the allowed classes whitelist.");
                }

                // Basic security checks
                if (! class_exists($className)) {
                    throw new SuperJsonException("Class '$className' does not exist.");
                }

                // Prevent instantiation of dangerous classes
                $dangerousClasses = [
                    'ReflectionClass', 'ReflectionMethod', 'ReflectionFunction',
                    'SplFileObject', 'DirectoryIterator', 'FilesystemIterator',
                    'PDO', 'mysqli', 'SQLite3',
                ];
                if (in_array($className, $dangerousClasses, true)) {
                    throw new SuperJsonException("Class '$className' is not allowed for security reasons.");
                }

                try {
                    $instance = new $className;
                    foreach ($data as $key => $value) {
                        if (property_exists($instance, $key)) {
                            $propPath = $path ? "$path.$key" : $key;
                            $instance->$key = $this->restore($value, $meta, $propPath);
                        }
                    }

                    return $instance;
                } catch (\Throwable $e) {
                    throw new SuperJsonException("Failed to restore class '$className': ".$e->getMessage());
                }
            }
        }

        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $itemPath = $path ? "$path.$key" : (string) $key;
                $result[$key] = $this->restore($value, $meta, $itemPath);
            }

            return $result;
        }

        return $data;
    }

    /**
     * Register default type transformers
     */
    protected function registerDefaultTransformers(): void
    {
        $this->registerTransformer(new Transformers\DateTransformer);
        $this->registerTransformer(new Transformers\BigIntTransformer);
        $this->registerTransformer(new Transformers\CollectionTransformer);
        $this->registerTransformer(new Transformers\MapTransformer);
        $this->registerTransformer(new Transformers\RegexTransformer);
        $this->registerTransformer(new Transformers\UrlTransformer);
        $this->registerTransformer(new Transformers\ErrorTransformer);
    }

    /**
     * Register a custom transformer
     */
    public function registerTransformer(TypeTransformer $transformer): void
    {
        $this->transformers[] = $transformer;
    }

    /**
     * Convert to HTML-safe JSON for Blade embedding
     */
    public function toHtml($data): string
    {
        $serialized = $this->serialize($data);

        return htmlspecialchars(json_encode($serialized), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate input for security issues
     */
    protected function validateInput($input): void
    {
        if (is_string($input)) {
            // Check for excessively long strings that could cause memory issues
            if (strlen($input) > 10 * 1024 * 1024) { // 10MB limit
                throw SuperJsonException::invalidInput('Input string too large', [
                    'size' => strlen($input),
                    'limit' => 10 * 1024 * 1024,
                ]);
            }

            // Basic sanity check for JSON structure
            if (! str_starts_with(trim($input), '{') && ! str_starts_with(trim($input), '[')) {
                throw SuperJsonException::invalidInput('Input does not appear to be valid JSON');
            }
        }

        if (is_array($input)) {
            // Check for nested array depth
            $this->validateArrayDepth($input, 0);

            // Check for excessively large arrays
            $maxArraySize = $this->config['security']['max_array_size'] ?? 1000;
            if (count($input) > $maxArraySize) {
                throw SuperJsonException::invalidInput('Input array too large', [
                    'size' => count($input),
                    'limit' => $maxArraySize,
                ]);
            }
        }
    }

    /**
     * Recursively validate array depth
     */
    protected function validateArrayDepth(array $data, int $depth): void
    {
        $maxDepth = $this->config['security']['max_depth'] ?? 10;
        if ($depth > $maxDepth) {
            throw SuperJsonException::invalidInput('Input nesting too deep', [
                'depth' => $depth,
                'limit' => $maxDepth,
            ]);
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $this->validateArrayDepth($value, $depth + 1);
            }
        }
    }
}
