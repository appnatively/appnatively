<?php

namespace CustomSniffs\Sniffs\Arrays;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use PHPCSUtils\Fixers\SpacesFixer;
use PHPCSUtils\Tokens\Collections;
use PHPCSUtils\Utils\Arrays;
use PHPCSUtils\Utils\PassedParameters;

/** Require multiline data arrays while keeping WordPress hook callbacks compact. */
class MultilineArraySniff implements Sniff {
    public function register(): array {
        return [
            T_ARRAY,
            T_OPEN_SHORT_ARRAY
        ];
    }

    public function process( File $phpcs_file, $stack_ptr ) {
        $braces = Arrays::getOpenClose( $phpcs_file, $stack_ptr );
        if ( false === $braces ) {
            // Array access and destructuring are not array declarations.
            return;
        }

        $items = PassedParameters::getParameters( $phpcs_file, $stack_ptr );
        if ( count( $items ) < 2 ) {
            return;
        }

        if ( $this->is_control_header( $phpcs_file, $stack_ptr ) ) {
            return;
        }

        // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPCS exposes this utility property in camelCase.
        $empty_tokens = Tokens::$emptyTokens;
        if ( $this->is_route_argument( $phpcs_file, $stack_ptr, $braces, $empty_tokens ) ) {
            return;
        }
        if ( count( $items ) === 2 && $this->is_hook_callback( $phpcs_file, $stack_ptr, $braces, $empty_tokens ) ) {
            $this->compact_hook_callback( $phpcs_file, $braces );
            return;
        }

        foreach ( $items as $item ) {
            $start = $phpcs_file->findNext( $empty_tokens, $item['start'], $item['end'] + 1, true );
            if ( false === $start ) {
                continue;
            }
            SpacesFixer::checkAndFix(
                $phpcs_file,
                $start,
                $phpcs_file->findPrevious( T_WHITESPACE, $start - 1, $braces['opener'] - 1, true ),
                'newline',
                'Expected %s before each item in a multi-item array. Found: %s',
                'ItemNewline',
                'error'
            );
        }
    }

    private function is_control_header( File $phpcs_file, int $stack_ptr ): bool {
        $tokens      = $phpcs_file->getTokens();
        $conditions  = $tokens[$stack_ptr]['conditions'] ?? [];
        $scope_start = empty( $conditions ) ? -1 : max( array_keys( $conditions ) );
        foreach ( $tokens[$stack_ptr]['nested_parenthesis'] ?? [] as $opener => $closer ) {
            // Do not exempt data in a closure or another body nested inside a header.
            if ( $opener < $scope_start ) {
                continue;
            }
            $owner = $tokens[$opener]['parenthesis_owner'] ?? null;
            if ( null !== $owner && isset( Collections::controlStructureTokens()[$tokens[$owner]['code']] ) ) {
                return true;
            }
        }
        return false;
    }

    private function is_route_argument( File $phpcs_file, int $stack_ptr, array $braces, array $empty_tokens ): bool {
        $tokens      = $phpcs_file->getTokens();
        $parentheses = $tokens[$stack_ptr]['nested_parenthesis'] ?? [];
        if ( empty( $parentheses ) ) {
            return false;
        }
        end( $parentheses );
        $method   = $phpcs_file->findPrevious( $empty_tokens, key( $parentheses ) - 1, null, true );
        $operator = $phpcs_file->findPrevious( $empty_tokens, $method - 1, null, true );
        if ( false === $operator || T_DOUBLE_COLON !== $tokens[$operator]['code'] ) {
            return false;
        }
        $class      = $phpcs_file->findPrevious( $empty_tokens, $operator - 1, null, true );
        $class_name = strtolower( ltrim( $tokens[$class]['content'], '\\' ) );
        $separator  = strrpos( $class_name, '\\' );
        if ( 'route' !== ( false === $separator ? $class_name : substr( $class_name, $separator + 1 ) ) ) {
            return false;
        }

        foreach ( PassedParameters::getParameters( $phpcs_file, $method ) as $parameter ) {
            $start = $phpcs_file->findNext( $empty_tokens, $parameter['start'], $parameter['end'] + 1, true );
            $end   = $phpcs_file->findPrevious( $empty_tokens, $parameter['end'], $parameter['start'] - 1, true );
            if ( $start === $stack_ptr && $end === $braces['closer'] ) {
                return true;
            }
        }
        return false;
    }

    private function is_hook_callback( File $phpcs_file, int $stack_ptr, array $braces, array $empty_tokens ): bool {
        $tokens      = $phpcs_file->getTokens();
        $parentheses = $tokens[$stack_ptr]['nested_parenthesis'] ?? [];
        if ( empty( $parentheses ) ) {
            return false;
        }

        end( $parentheses );
        $opener   = key( $parentheses );
        $function = $phpcs_file->findPrevious( $empty_tokens, $opener - 1, null, true );
        $name     = strtolower( ltrim( $tokens[$function]['content'], '\\' ) );
        if ( 'add_filter' !== $name && 'add_action' !== $name ) {
            return false;
        }
        $before = $phpcs_file->findPrevious( $empty_tokens, $function - 1, null, true );
        if ( false !== $before ) {
            if ( T_OBJECT_OPERATOR === $tokens[$before]['code'] || T_DOUBLE_COLON === $tokens[$before]['code'] || '?->' === $tokens[$before]['content'] ) {
                return false;
            }
            if ( T_NS_SEPARATOR === $tokens[$before]['code'] ) {
                $namespace = $phpcs_file->findPrevious( $empty_tokens, $before - 1, null, true );
                if ( false !== $namespace && ( T_STRING === $tokens[$namespace]['code'] || T_NAMESPACE === $tokens[$namespace]['code'] ) ) {
                    return false;
                }
            }
        }

        $parameters = PassedParameters::getParameters( $phpcs_file, $function );
        $callback   = PassedParameters::getParameterFromStack( $parameters, 2, ['callback'] );
        if ( false === $callback ) {
            return false;
        }
        $start = $phpcs_file->findNext( $empty_tokens, $callback['start'], $callback['end'] + 1, true );
        $end   = $phpcs_file->findPrevious( $empty_tokens, $callback['end'], $callback['start'] - 1, true );
        return $start === $stack_ptr && $end === $braces['closer'];
    }

    private function compact_hook_callback( File $phpcs_file, array $braces ): void {
        $tokens   = $phpcs_file->getTokens();
        $contents = '';
        $last     = $phpcs_file->findPrevious( T_WHITESPACE, $braces['closer'] - 1, $braces['opener'], true );
        for ( $index = $braces['opener'] + 1; $index < $braces['closer']; ++$index ) {
            // Comments and nested data arrays need their original line boundaries.
            // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPCS exposes this utility property in camelCase.
            if ( isset( Tokens::$commentTokens[$tokens[$index]['code']] ) || T_ARRAY === $tokens[$index]['code'] || T_OPEN_SHORT_ARRAY === $tokens[$index]['code'] || ( T_WHITESPACE !== $tokens[$index]['code'] && strpos( $tokens[$index]['content'], "\n" ) !== false ) ) {
                return;
            }
            if ( $index === $last && T_COMMA === $tokens[$index]['code'] ) {
                continue;
            }
            if ( T_WHITESPACE === $tokens[$index]['code'] ) {
                if ( '' !== $contents && substr( $contents, -1 ) !== ' ' ) {
                    $contents .= ' ';
                }
            } else {
                $contents .= $tokens[$index]['content'];
            }
        }

        $replacement = $tokens[$braces['opener']]['content'] . trim( $contents ) . $tokens[$braces['closer']]['content'];
        if ( $phpcs_file->getTokensAsString( $braces['opener'], $braces['closer'] - $braces['opener'] + 1 ) === $replacement ) {
            return;
        }
        if ( ! $phpcs_file->addFixableError( 'Method callbacks passed to add_filter() or add_action() must be on one line.', $braces['opener'], 'HookCallbackSingleLine' ) ) {
            return;
        }
        $phpcs_file->fixer->beginChangeset();
        $phpcs_file->fixer->replaceToken( $braces['opener'], $replacement );
        for ( $index = $braces['opener'] + 1; $index <= $braces['closer']; ++$index ) {
            $phpcs_file->fixer->replaceToken( $index, '' );
        }
        $phpcs_file->fixer->endChangeset();
    }
}
