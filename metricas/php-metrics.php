<?php

declare(strict_types=1);

/**
 * Analizador estático de métricas para proyectos PHP.
 *
 * Métricas:
 * - Líneas físicas.
 * - Líneas de código, excluyendo comentarios y espacios.
 * - Clases declaradas.
 * - Funciones y métodos con nombre.
 * - Funciones anónimas y funciones flecha.
 * - Complejidad ciclomática aproximada.
 *
 * Uso:
 *
 * php php-metrics.php /ruta/al/proyecto
 * php php-metrics.php /ruta/al/proyecto --json
 * php php-metrics.php /ruta/al/proyecto --module-depth=2
 * php php-metrics.php /ruta/al/proyecto --exclude=vendor,node_modules,storage
 * php php-metrics.php /ruta/al/proyecto --json --output=reporte.json
 *
 * Requiere PHP 8.0 o superior.
 */

final class PhpFileMetrics
{
    public function __construct(
        public readonly string $file,
        public readonly string $module,
        public readonly int $physicalLines,
        public readonly int $codeLines,
        public readonly int $classes,
        public readonly int $functions,
        public readonly int $closures,
        public readonly int $cyclomaticComplexity,
        public readonly ?string $parseError = null,
    ) {
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'module' => $this->module,
            'physical_lines' => $this->physicalLines,
            'code_lines' => $this->codeLines,
            'classes' => $this->classes,
            'functions' => $this->functions,
            'closures' => $this->closures,
            'cyclomatic_complexity' => $this->cyclomaticComplexity,
            'parse_error' => $this->parseError,
        ];
    }
}

final class ModuleMetrics
{
    public int $files = 0;
    public int $physicalLines = 0;
    public int $codeLines = 0;
    public int $classes = 0;
    public int $functions = 0;
    public int $closures = 0;
    public int $cyclomaticComplexity = 0;
    public int $filesWithErrors = 0;

    public function __construct(
        public readonly string $module,
    ) {
    }

    public function add(PhpFileMetrics $metrics): void
    {
        $this->files++;
        $this->physicalLines += $metrics->physicalLines;
        $this->codeLines += $metrics->codeLines;
        $this->classes += $metrics->classes;
        $this->functions += $metrics->functions;
        $this->closures += $metrics->closures;
        $this->cyclomaticComplexity += $metrics->cyclomaticComplexity;

        if ($metrics->parseError !== null) {
            $this->filesWithErrors++;
        }
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'files' => $this->files,
            'physical_lines' => $this->physicalLines,
            'code_lines' => $this->codeLines,
            'classes' => $this->classes,
            'functions' => $this->functions,
            'closures' => $this->closures,
            'cyclomatic_complexity' => $this->cyclomaticComplexity,
            'files_with_errors' => $this->filesWithErrors,
        ];
    }
}

final class PhpMetricsAnalyzer
{
    /**
     * @param list<string> $excludedDirectories
     */
    public function __construct(
        private readonly string $rootDirectory,
        private readonly int $moduleDepth = 1,
        private readonly array $excludedDirectories = [
            '.git',
            '.idea',
            '.vscode',
            'vendor',
            'node_modules',
        ],
    ) {
        if (!is_dir($this->rootDirectory)) {
            throw new InvalidArgumentException(
                sprintf('El directorio "%s" no existe.', $this->rootDirectory)
            );
        }

        if ($this->moduleDepth < 1) {
            throw new InvalidArgumentException(
                'La profundidad del módulo debe ser mayor o igual que 1.'
            );
        }
    }

    /**
     * @return list<PhpFileMetrics>
     */
    public function analyze(): array
    {
        $results = [];

        foreach ($this->findPhpFiles() as $file) {
            $results[] = $this->analyzeFile($file);
        }

        usort(
            $results,
            static fn (PhpFileMetrics $a, PhpFileMetrics $b): int =>
                strcmp($a->file, $b->file)
        );

        return $results;
    }

    /**
     * @return list<string>
     */
    private function findPhpFiles(): array
    {
        $files = [];

        $directoryIterator = new RecursiveDirectoryIterator(
            $this->rootDirectory,
            FilesystemIterator::SKIP_DOTS
        );

        $filterIterator = new RecursiveCallbackFilterIterator(
            $directoryIterator,
            function (SplFileInfo $current): bool {
                if ($current->isDir()) {
                    return !in_array(
                        $current->getFilename(),
                        $this->excludedDirectories,
                        true
                    );
                }

                return true;
            }
        );

        $iterator = new RecursiveIteratorIterator($filterIterator);

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo || !$item->isFile()) {
                continue;
            }

            if (strtolower($item->getExtension()) !== 'php') {
                continue;
            }

            $realPath = $item->getRealPath();

            if ($realPath !== false) {
                $files[] = $realPath;
            }
        }

        sort($files);

        return $files;
    }

    private function analyzeFile(string $absolutePath): PhpFileMetrics
    {
        $source = file_get_contents($absolutePath);

        if ($source === false) {
            throw new RuntimeException(
                sprintf('No fue posible leer el archivo "%s".', $absolutePath)
            );
        }

        $parseError = null;

        try {
            $tokens = token_get_all($source, TOKEN_PARSE);
        } catch (ParseError $exception) {
            /*
             * Aunque el archivo tenga un error sintáctico, se vuelve a
             * tokenizar sin TOKEN_PARSE para obtener métricas aproximadas.
             */
            $parseError = $exception->getMessage();
            $tokens = token_get_all($source);
        }

        $physicalLines = $this->countPhysicalLines($source);
        $codeLines = $this->countCodeLines($tokens);

        $classes = 0;
        $functions = 0;
        $closures = 0;
        $decisionPoints = 0;
        $callableScopes = 0;

        foreach ($tokens as $index => $token) {
            if (is_array($token)) {
                $tokenId = $token[0];

                if ($tokenId === T_CLASS && $this->isNamedClass($tokens, $index)) {
                    $classes++;
                    continue;
                }

                if ($tokenId === T_FUNCTION) {
                    $callableScopes++;

                    if ($this->isNamedFunction($tokens, $index)) {
                        $functions++;
                    } else {
                        $closures++;
                    }

                    continue;
                }

                if (defined('T_FN') && $tokenId === T_FN) {
                    $callableScopes++;
                    $closures++;
                    continue;
                }

                if ($this->isDecisionToken($tokenId)) {
                    $decisionPoints++;
                }

                continue;
            }

            /*
             * token_get_all() devuelve algunos operadores y delimitadores
             * como cadenas simples. El signo ? puede representar un ternario.
             */
            if ($token === '?' && $this->isLikelyTernary($tokens, $index)) {
                $decisionPoints++;
            }
        }

        /*
         * Cada función, método, closure o función flecha tiene una
         * complejidad base de 1.
         *
         * Cuando un archivo no contiene funciones, se asigna una complejidad
         * base de 1 correspondiente a su ámbito global.
         */
        $baseComplexity = max(1, $callableScopes);
        $cyclomaticComplexity = $baseComplexity + $decisionPoints;

        return new PhpFileMetrics(
            file: $this->relativePath($absolutePath),
            module: $this->resolveModule($absolutePath),
            physicalLines: $physicalLines,
            codeLines: $codeLines,
            classes: $classes,
            functions: $functions,
            closures: $closures,
            cyclomaticComplexity: $cyclomaticComplexity,
            parseError: $parseError,
        );
    }

    private function countPhysicalLines(string $source): int
    {
        if ($source === '') {
            return 0;
        }

        return substr_count($source, "\n") + 1;
    }

    /**
     * Cuenta las líneas que contienen tokens significativos.
     *
     * Se excluyen:
     * - Espacios en blanco.
     * - Comentarios.
     * - Comentarios PHPDoc.
     * - Etiquetas de apertura y cierre de PHP.
     *
     * @param array<int, array{0:int,1:string,2:int}|string> $tokens
     */
    private function countCodeLines(array $tokens): int
    {
        /** @var array<int, true> $codeLineNumbers */
        $codeLineNumbers = [];

        $currentLine = 1;

        $ignoredTokens = [
            T_WHITESPACE,
            T_COMMENT,
            T_DOC_COMMENT,
            T_OPEN_TAG,
            T_CLOSE_TAG,
        ];

        if (defined('T_OPEN_TAG_WITH_ECHO')) {
            $ignoredTokens[] = T_OPEN_TAG_WITH_ECHO;
        }

        foreach ($tokens as $token) {
            if (is_array($token)) {
                [$tokenId, $content, $startLine] = $token;

                $numberOfNewLines = substr_count($content, "\n");
                $endLine = $startLine + $numberOfNewLines;

                if (!in_array($tokenId, $ignoredTokens, true)) {
                    for ($line = $startLine; $line <= $endLine; $line++) {
                        $codeLineNumbers[$line] = true;
                    }
                }

                $currentLine = $endLine;
                continue;
            }

            if (trim($token) !== '') {
                $codeLineNumbers[$currentLine] = true;
            }

            $currentLine += substr_count($token, "\n");
        }

        return count($codeLineNumbers);
    }

    /**
     * Determina si T_CLASS corresponde a una declaración de clase con nombre.
     *
     * Excluye:
     * - Clases anónimas: new class { ... }
     * - La constante de clase: MiClase::class
     *
     * @param array<int, array{0:int,1:string,2:int}|string> $tokens
     */
    private function isNamedClass(array $tokens, int $index): bool
    {
        $previous = $this->previousSignificantToken($tokens, $index);

        if ($this->tokenMatches($previous, T_NEW)) {
            return false;
        }

        if ($this->tokenMatches($previous, T_DOUBLE_COLON)) {
            return false;
        }

        $next = $this->nextSignificantToken($tokens, $index);

        return is_array($next) && $next[0] === T_STRING;
    }

    /**
     * Determina si T_FUNCTION tiene un nombre.
     *
     * @param array<int, array{0:int,1:string,2:int}|string> $tokens
     */
    private function isNamedFunction(array $tokens, int $index): bool
    {
        $nextIndex = $this->nextSignificantIndex($tokens, $index);

        if ($nextIndex === null) {
            return false;
        }

        $next = $tokens[$nextIndex];

        /*
         * Una función puede devolver por referencia:
         *
         * function &nombre()
         */
        if ($this->isAmpersandToken($next)) {
            $nextIndex = $this->nextSignificantIndex($tokens, $nextIndex);

            if ($nextIndex === null) {
                return false;
            }

            $next = $tokens[$nextIndex];
        }

        return is_array($next) && $next[0] === T_STRING;
    }

    private function isDecisionToken(int $tokenId): bool
    {
        $decisionTokens = [
            T_IF,
            T_ELSEIF,
            T_FOR,
            T_FOREACH,
            T_WHILE,
            T_DO,
            T_CASE,
            T_CATCH,
            T_BOOLEAN_AND,
            T_BOOLEAN_OR,
            T_LOGICAL_AND,
            T_LOGICAL_OR,
        ];

        if (defined('T_COALESCE')) {
            $decisionTokens[] = T_COALESCE;
        }

        /*
         * match se considera un punto de decisión aproximado.
         * Los brazos individuales no se contabilizan por separado.
         */
        if (defined('T_MATCH')) {
            $decisionTokens[] = T_MATCH;
        }

        return in_array($tokenId, $decisionTokens, true);
    }

    /**
     * Intenta diferenciar el operador ternario de los tipos anulables:
     *
     * $resultado = $condicion ? 'sí' : 'no';
     * function ejemplo(?string $valor): ?int
     *
     * Esta identificación es deliberadamente heurística.
     *
     * @param array<int, array{0:int,1:string,2:int}|string> $tokens
     */
    private function isLikelyTernary(array $tokens, int $index): bool
    {
        $previous = $this->previousSignificantToken($tokens, $index);
        $next = $this->nextSignificantToken($tokens, $index);

        if ($previous === null || $next === null) {
            return false;
        }

        /*
         * ?:
         *
         * El ternario abreviado sigue siendo una decisión.
         */
        if ($next === ':') {
            return true;
        }

        /*
         * En tipos anulables, ? suele aparecer después de (, ,, :, |, &,
         * modificadores de visibilidad o palabras reservadas.
         */
        $invalidPreviousStrings = [
            '(',
            '[',
            '{',
            ',',
            ':',
            ';',
            '=',
            '|',
            '&',
            '!',
            '?',
        ];

        if (is_string($previous) && in_array($previous, $invalidPreviousStrings, true)) {
            return false;
        }

        if (is_array($previous)) {
            $invalidPreviousTokenIds = [
                T_FUNCTION,
                T_FN,
                T_PUBLIC,
                T_PROTECTED,
                T_PRIVATE,
                T_STATIC,
                T_ABSTRACT,
                T_FINAL,
                T_CONST,
                T_NEW,
                T_RETURN,
                T_YIELD,
                T_AS,
            ];

            if (
                defined('T_READONLY') &&
                $previous[0] === T_READONLY
            ) {
                return false;
            }

            if (in_array($previous[0], $invalidPreviousTokenIds, true)) {
                return false;
            }
        }

        /*
         * Cuando el elemento anterior representa un valor o expresión,
         * probablemente se trata de un operador ternario.
         */
        if (is_string($previous)) {
            return in_array($previous, [')', ']', '}'], true);
        }

        $validPreviousTokenIds = [
            T_VARIABLE,
            T_STRING,
            T_LNUMBER,
            T_DNUMBER,
            T_CONSTANT_ENCAPSED_STRING,
            
            
            
            T_INC,
            T_DEC,
        ];

        return in_array($previous[0], $validPreviousTokenIds, true);
    }

    /**
     * @param array<int, array{0:int,1:string,2:int}|string> $tokens
     */
    private function previousSignificantToken(array $tokens, int $index): array|string|null
    {
        for ($position = $index - 1; $position >= 0; $position--) {
            if (!$this->isIgnorableToken($tokens[$position])) {
                return $tokens[$position];
            }
        }

        return null;
    }

    /**
     * @param array<int, array{0:int,1:string,2:int}|string> $tokens
     */
    private function nextSignificantToken(array $tokens, int $index): array|string|null
    {
        $nextIndex = $this->nextSignificantIndex($tokens, $index);

        return $nextIndex === null ? null : $tokens[$nextIndex];
    }

    /**
     * @param array<int, array{0:int,1:string,2:int}|string> $tokens
     */
    private function nextSignificantIndex(array $tokens, int $index): ?int
    {
        $numberOfTokens = count($tokens);

        for ($position = $index + 1; $position < $numberOfTokens; $position++) {
            if (!$this->isIgnorableToken($tokens[$position])) {
                return $position;
            }
        }

        return null;
    }

    /**
     * @param array{0:int,1:string,2:int}|string $token
     */
    private function isIgnorableToken(array|string $token): bool
    {
        if (is_string($token)) {
            return trim($token) === '';
        }

        return in_array(
            $token[0],
            [
                T_WHITESPACE,
                T_COMMENT,
                T_DOC_COMMENT,
                T_OPEN_TAG,
                T_CLOSE_TAG,
            ],
            true
        );
    }

    /**
     * @param array{0:int,1:string,2:int}|string|null $token
     */
    private function tokenMatches(array|string|null $token, int $tokenId): bool
    {
        return is_array($token) && $token[0] === $tokenId;
    }

    /**
     * @param array{0:int,1:string,2:int}|string $token
     */
    private function isAmpersandToken(array|string $token): bool
    {
        if ($token === '&') {
            return true;
        }

        if (!is_array($token)) {
            return false;
        }

        $ampersandTokenIds = [];

        if (defined('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG')) {
            $ampersandTokenIds[] = T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG;
        }

        if (defined('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG')) {
            $ampersandTokenIds[] = T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG;
        }

        return in_array($token[0], $ampersandTokenIds, true);
    }

    private function relativePath(string $absolutePath): string
    {
        $normalizedRoot = rtrim(
            str_replace('\\', '/', realpath($this->rootDirectory) ?: $this->rootDirectory),
            '/'
        );

        $normalizedFile = str_replace('\\', '/', $absolutePath);

        if (str_starts_with($normalizedFile, $normalizedRoot . '/')) {
            return substr($normalizedFile, strlen($normalizedRoot) + 1);
        }

        return $normalizedFile;
    }

    private function resolveModule(string $absolutePath): string
    {
        $relativePath = $this->relativePath($absolutePath);
        $directory = dirname(str_replace('\\', '/', $relativePath));

        if ($directory === '.' || $directory === '') {
            return '(raíz)';
        }

        $segments = array_values(
            array_filter(
                explode('/', $directory),
                static fn (string $segment): bool => $segment !== ''
            )
        );

        if ($segments === []) {
            return '(raíz)';
        }

        return implode(
            '/',
            array_slice($segments, 0, $this->moduleDepth)
        );
    }
}

final class ConsoleReport
{
    /**
     * @param list<PhpFileMetrics> $files
     */
    public static function print(array $files): void
    {
        self::printFileMetrics($files);

        echo PHP_EOL;
        self::printModuleMetrics(self::groupByModule($files));

        echo PHP_EOL;
        self::printSummary($files);
    }

    /**
     * @param list<PhpFileMetrics> $files
     */
    private static function printFileMetrics(array $files): void
    {
        echo 'MÉTRICAS POR ARCHIVO' . PHP_EOL;
        echo str_repeat('=', 110) . PHP_EOL;

        $rows = [];

        foreach ($files as $metrics) {
            $rows[] = [
                $metrics->file,
                $metrics->module,
                $metrics->physicalLines,
                $metrics->codeLines,
                $metrics->classes,
                $metrics->functions,
                $metrics->closures,
                $metrics->cyclomaticComplexity,
                $metrics->parseError === null ? 'OK' : 'ERROR',
            ];
        }

        self::printTable(
            [
                'Archivo',
                'Módulo',
                'Líneas',
                'LDC',
                'Clases',
                'Funciones',
                'Closures',
                'CC',
                'Estado',
            ],
            $rows
        );
    }

    /**
     * @param array<string, ModuleMetrics> $modules
     */
    private static function printModuleMetrics(array $modules): void
    {
        echo 'MÉTRICAS POR MÓDULO' . PHP_EOL;
        echo str_repeat('=', 110) . PHP_EOL;

        $rows = [];

        foreach ($modules as $module) {
            $rows[] = [
                $module->module,
                $module->files,
                $module->physicalLines,
                $module->codeLines,
                $module->classes,
                $module->functions,
                $module->closures,
                $module->cyclomaticComplexity,
                $module->filesWithErrors,
            ];
        }

        self::printTable(
            [
                'Módulo',
                'Archivos',
                'Líneas',
                'LDC',
                'Clases',
                'Funciones',
                'Closures',
                'CC',
                'Errores',
            ],
            $rows
        );
    }

    /**
     * @param list<PhpFileMetrics> $files
     */
    private static function printSummary(array $files): void
    {
        $totalPhysicalLines = 0;
        $totalCodeLines = 0;
        $totalClasses = 0;
        $totalFunctions = 0;
        $totalClosures = 0;
        $totalComplexity = 0;
        $filesWithErrors = 0;

        foreach ($files as $metrics) {
            $totalPhysicalLines += $metrics->physicalLines;
            $totalCodeLines += $metrics->codeLines;
            $totalClasses += $metrics->classes;
            $totalFunctions += $metrics->functions;
            $totalClosures += $metrics->closures;
            $totalComplexity += $metrics->cyclomaticComplexity;

            if ($metrics->parseError !== null) {
                $filesWithErrors++;
            }
        }

        echo 'RESUMEN GENERAL' . PHP_EOL;
        echo str_repeat('=', 50) . PHP_EOL;
        echo sprintf("Archivos PHP:              %d\n", count($files));
        echo sprintf("Líneas físicas:            %d\n", $totalPhysicalLines);
        echo sprintf("Líneas de código:          %d\n", $totalCodeLines);
        echo sprintf("Clases:                    %d\n", $totalClasses);
        echo sprintf("Funciones y métodos:       %d\n", $totalFunctions);
        echo sprintf("Closures y funciones fn:   %d\n", $totalClosures);
        echo sprintf("Complejidad aproximada:    %d\n", $totalComplexity);
        echo sprintf("Archivos con errores:      %d\n", $filesWithErrors);
    }

    /**
     * @param list<PhpFileMetrics> $files
     *
     * @return array<string, ModuleMetrics>
     */
    public static function groupByModule(array $files): array
    {
        $modules = [];

        foreach ($files as $metrics) {
            if (!isset($modules[$metrics->module])) {
                $modules[$metrics->module] = new ModuleMetrics(
                    $metrics->module
                );
            }

            $modules[$metrics->module]->add($metrics);
        }

        ksort($modules);

        return $modules;
    }

    /**
     * @param list<string> $headers
     * @param list<list<int|string>> $rows
     */
    private static function printTable(array $headers, array $rows): void
    {
        $widths = array_map('strlen', $headers);

        foreach ($rows as $row) {
            foreach ($row as $column => $value) {
                $widths[$column] = max(
                    $widths[$column],
                    strlen((string) $value)
                );
            }
        }

        self::printSeparator($widths);
        self::printRow($headers, $widths);
        self::printSeparator($widths);

        foreach ($rows as $row) {
            self::printRow($row, $widths);
        }

        self::printSeparator($widths);
    }

    /**
     * @param list<int> $widths
     */
    private static function printSeparator(array $widths): void
    {
        echo '+';

        foreach ($widths as $width) {
            echo str_repeat('-', $width + 2) . '+';
        }

        echo PHP_EOL;
    }

    /**
     * @param list<int|string> $row
     * @param list<int> $widths
     */
    private static function printRow(array $row, array $widths): void
    {
        echo '|';

        foreach ($row as $column => $value) {
            echo ' '
                . str_pad((string) $value, $widths[$column])
                . ' |';
        }

        echo PHP_EOL;
    }
}

/**
 * @return array{
 *     root:string,
 *     json:bool,
 *     moduleDepth:int,
 *     excludedDirectories:list<string>,
 *     output:?string
 * }
 */
function parseArguments(array $arguments): array
{
    array_shift($arguments);

    $root = null;
    $json = false;
    $moduleDepth = 1;
    $output = null;

    $excludedDirectories = [
        '.git',
        '.idea',
        '.vscode',
        'vendor',
        'node_modules',
    ];

    foreach ($arguments as $argument) {
        if ($argument === '--json') {
            $json = true;
            continue;
        }

        if (str_starts_with($argument, '--module-depth=')) {
            $value = substr($argument, strlen('--module-depth='));
            $moduleDepth = max(1, (int) $value);
            continue;
        }

        if (str_starts_with($argument, '--exclude=')) {
            $value = substr($argument, strlen('--exclude='));

            $additionalExclusions = array_values(
                array_filter(
                    array_map('trim', explode(',', $value)),
                    static fn (string $directory): bool => $directory !== ''
                )
            );

            $excludedDirectories = array_values(
                array_unique(
                    array_merge(
                        $excludedDirectories,
                        $additionalExclusions
                    )
                )
            );

            continue;
        }

        if (str_starts_with($argument, '--output=')) {
            $output = substr($argument, strlen('--output='));
            continue;
        }

        if ($argument === '--help' || $argument === '-h') {
            printHelp();
            exit(0);
        }

        if (!str_starts_with($argument, '--') && $root === null) {
            $root = $argument;
        }
    }

    return [
        'root' => $root ?? getcwd(),
        'json' => $json,
        'moduleDepth' => $moduleDepth,
        'excludedDirectories' => $excludedDirectories,
        'output' => $output,
    ];
}

function printHelp(): void
{
    echo <<<HELP
Analizador de métricas para proyectos PHP

Uso:
  php php-metrics.php [directorio] [opciones]

Opciones:
  --json                 Genera la salida en formato JSON.
  --module-depth=N       Profundidad usada para agrupar módulos.
  --exclude=a,b,c        Directorios adicionales que se excluirán.
  --output=archivo       Guarda el resultado en un archivo.
  --help, -h             Muestra esta ayuda.

Ejemplos:
  php php-metrics.php .
  php php-metrics.php /var/www/proyecto
  php php-metrics.php . --module-depth=2
  php php-metrics.php . --exclude=storage,cache
  php php-metrics.php . --json --output=metrics.json

HELP;
}

/**
 * @param list<PhpFileMetrics> $files
 *
 * @return array<string, mixed>
 */
function buildJsonReport(array $files): array
{
    $modules = ConsoleReport::groupByModule($files);

    $summary = [
        'files' => count($files),
        'physical_lines' => 0,
        'code_lines' => 0,
        'classes' => 0,
        'functions' => 0,
        'closures' => 0,
        'cyclomatic_complexity' => 0,
        'files_with_errors' => 0,
    ];

    foreach ($files as $metrics) {
        $summary['physical_lines'] += $metrics->physicalLines;
        $summary['code_lines'] += $metrics->codeLines;
        $summary['classes'] += $metrics->classes;
        $summary['functions'] += $metrics->functions;
        $summary['closures'] += $metrics->closures;
        $summary['cyclomatic_complexity'] +=
            $metrics->cyclomaticComplexity;

        if ($metrics->parseError !== null) {
            $summary['files_with_errors']++;
        }
    }

    return [
        'generated_at' => date(DATE_ATOM),
        'summary' => $summary,
        'modules' => array_map(
            static fn (ModuleMetrics $module): array => $module->toArray(),
            array_values($modules)
        ),
        'files' => array_map(
            static fn (PhpFileMetrics $file): array => $file->toArray(),
            $files
        ),
    ];
}

function main(array $arguments): int
{
    try {
        $options = parseArguments($arguments);

        $root = realpath($options['root']);

        if ($root === false || !is_dir($root)) {
            throw new InvalidArgumentException(
                sprintf(
                    'La ruta "%s" no es un directorio válido.',
                    $options['root']
                )
            );
        }

        $analyzer = new PhpMetricsAnalyzer(
            rootDirectory: $root,
            moduleDepth: $options['moduleDepth'],
            excludedDirectories: $options['excludedDirectories'],
        );

        $files = $analyzer->analyze();

        if ($options['json']) {
            $report = buildJsonReport($files);

            $content = json_encode(
                $report,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ) . PHP_EOL;
        } else {
            ob_start();
            ConsoleReport::print($files);
            $content = (string) ob_get_clean();
        }

        if ($options['output'] !== null) {
            $bytes = file_put_contents($options['output'], $content);

            if ($bytes === false) {
                throw new RuntimeException(
                    sprintf(
                        'No fue posible escribir el archivo "%s".',
                        $options['output']
                    )
                );
            }

            echo sprintf(
                "Reporte guardado en: %s\n",
                $options['output']
            );
        } else {
            echo $content;
        }

        return 0;
    } catch (Throwable $exception) {
        fwrite(
            STDERR,
            sprintf(
                "Error: %s\n",
                $exception->getMessage()
            )
        );

        return 1;
    }
}

exit(main($argv));
