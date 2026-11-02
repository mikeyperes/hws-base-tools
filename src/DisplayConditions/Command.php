<?php
namespace HWS\BaseTools\DisplayConditions;
final class Command {
    /** List named site conditions. */
    public function list($args,$assoc): void { \WP_CLI::line(wp_json_encode(Conditions::all(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); }
    /** Get a named condition. */
    public function get($args): void { $all=Conditions::all(); if(!isset($all[$args[0]??'']))\WP_CLI::error('Unknown condition.'); \WP_CLI::line(wp_json_encode($all[$args[0]],JSON_PRETTY_PRINT)); }
    /** Set a definition: <slug> <json>. */
    public function set($args): void {try{ $d=json_decode($args[1]??'',true);if(!is_array($d))throw new \InvalidArgumentException('Expected JSON object.');Conditions::save($args[0]??'',$d);\WP_CLI::success('Condition saved.'); }catch(\InvalidArgumentException $e){\WP_CLI::error($e->getMessage());} }
    /** Delete a definition. Elements referencing it revert to None behavior. */
    public function delete($args): void { $all=Conditions::all();unset($all[$args[0]??'']);update_option(Conditions::OPTION,$all,false);Conditions::clear();\WP_CLI::success('Condition deleted.'); }
    /**
     * Migrate all non-revision Elementor documents, reporting unsupported rules unchanged.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Preview without writing.
     *
     * @subcommand migrate-jetengine
     */
    public function migrate($args,$assoc): void { \WP_CLI::line(wp_json_encode(Migration::run(isset($assoc['dry-run'])),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); }
}
