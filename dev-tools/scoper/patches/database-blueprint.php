<?php

declare(strict_types=1);

/** Keep the schema constraints supported by the pinned wpmvc/database 2.0.0 build. */
return static function ( string $file_path, string $prefix, string $contents ): string {
    $normalized_path = str_replace( '\\', '/', $file_path );
    if ( ! preg_match( '~(?:^|/)vendor-src/wpmvc/database/src/Schema/Blueprint\.php$~', $normalized_path ) ) {
        return $contents;
    }

    // Pin the upstream source, not generated formatting. Dependency changes need explicit review.
    if ( hash_file( 'sha256', $file_path ) !== '8b95a7914daf09cd1899b42d3996f0d8e524846e324b82d441be8b5b9036f5d0'
        || strpos( $contents, 'namespace ' . $prefix . '\\WpMVC\\Database\\Schema;' ) === false ) {
        throw new RuntimeException( 'Unexpected WpMVC Blueprint source; review the schema patch before building.' );
    }

    $long_text_before = <<<'PHP'
    /**
     * Add a LONGTEXT column.
     *
     * @param string $name
     * @return self
     */
    public function long_text(string $name) : self
    {
        $this->add_column("{$name} LONGTEXT");
        return $this;
    }
PHP;
    $long_text_after = <<<'PHP'
    /**
     * Add a LONGTEXT column.
     *
     * @param string $name
     * @param bool $nullable Preserve nullable columns unless explicitly disabled.
     * @return self
     */
    public function long_text(string $name, bool $nullable = true) : self
    {
        $nullability = $nullable ? '' : ' NOT NULL';
        $this->add_column("{$name} LONGTEXT{$nullability}");
        return $this;
    }
    /**
     * Set the storage engine for table creation.
     *
     * @param string $engine
     * @return self
     */
    public function engine(string $engine) : self
    {
        if (!\preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $engine)) {
            throw new \InvalidArgumentException('Invalid database table engine.');
        }
        $this->engine = $engine;
        return $this;
    }
PHP;
    $sql_before = <<<'PHP'
        return "CREATE TABLE {$this->table_name} (\n    {$body}\n) {$this->charset_collate};";
PHP;
    $sql_after = <<<'PHP'
        $engine = $this->engine === '' ? '' : " ENGINE={$this->engine}";
        return "CREATE TABLE {$this->table_name} (\n    {$body}\n) {$this->charset_collate}{$engine};";
PHP;
    $replacements = [
        '    protected string $charset_collate;' => "    protected string \$charset_collate;\n    protected string \$engine = '';",
        $long_text_before                        => $long_text_after,
        $sql_before                              => $sql_after,
    ];
    foreach ( $replacements as $before => $after ) {
        if ( substr_count( $contents, $before ) !== 1 ) {
            throw new RuntimeException( 'Unexpected scoped WpMVC Blueprint structure; schema patch was not applied.' );
        }
        $contents = str_replace( $before, $after, $contents );
    }

    return $contents;
};
