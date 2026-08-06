<?php

namespace HWS\BaseTools\QuickStart;

use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRunner;

/** Executes exactly the batch-enabled items from one HWS Quick Start profile. */
final class QuickStartBatchService {
    /** @return array<string,mixed> */
    public function run( string $profile_id = QuickStartProfileRegistry::DEFAULT_PROFILE ): array {
        $config     = QuickStartModule::config();
        $profile_id = $config->resolve_template_id( $profile_id );
        $runner     = new GettingStartedChecklistRunner( $config );
        $items      = [];
        $passed     = 0;
        $failed     = 0;
        $skipped    = 0;
        $aborted    = false;
        $abort_item = '';
        $queue      = [];

        foreach ( $config->template_steps( $profile_id ) as $step ) {
            if ( $step->has_subtasks() ) {
                foreach ( $step->subtasks as $subtask ) {
                    $queue[] = [
                        'step_id'      => $step->id,
                        'subtask_id'   => $subtask->id,
                        'label'        => $subtask->label,
                        'type'         => $subtask->type,
                        'batch_enabled'=> $subtask->batch_enabled,
                    ];
                }
                continue;
            }

            $queue[] = [
                'step_id'       => $step->id,
                'subtask_id'    => '',
                'label'         => $step->label,
                'type'          => $step->type,
                'batch_enabled' => $step->batch_enabled,
            ];
        }

        foreach ( $queue as $queued ) {
            if ( ! $queued['batch_enabled'] ) {
                $items[] = array_merge( $queued, [ 'status' => 'skipped', 'message' => 'Individual-only task skipped by batch.' ] );
                $skipped++;
                continue;
            }

            if ( $aborted ) {
                $items[] = array_merge(
                    $queued,
                    [
                        'status'  => 'skipped',
                        'message' => 'Not attempted because an earlier mutation failed.',
                        'blocked_by' => $abort_item,
                    ]
                );
                $skipped++;
                continue;
            }

            $result  = $runner->run_item( (string) $queued['step_id'], (string) $queued['subtask_id'], [], $profile_id );
            $items[] = array_merge( $queued, $result );
            if ( ! empty( $result['success'] ) ) {
                $passed++;
                continue;
            }

            $failed++;
            if ( self::is_mutation_type( (string) $queued['type'] ) ) {
                $aborted    = true;
                $abort_item = '' !== (string) $queued['subtask_id']
                    ? (string) $queued['step_id'] . ':' . (string) $queued['subtask_id']
                    : (string) $queued['step_id'];
            }
        }

        $message = sprintf( 'Quick Start finished: %d passed, %d failed, %d skipped.', $passed, $failed, $skipped );
        if ( $aborted ) {
            $message = 'Quick Start stopped after a mutation failed at ' . $abort_item . '. ' . $message;
        }

        return [
            'success' => 0 === $failed,
            'message' => $message,
            'profile' => $profile_id,
            'counts'  => compact( 'passed', 'failed', 'skipped' ),
            'aborted' => $aborted,
            'abort_item' => $abort_item,
            'items'   => $items,
        ];
    }

    public function text_log( string $profile_id = QuickStartProfileRegistry::DEFAULT_PROFILE ): string {
        $result = $this->run( $profile_id );
        $lines  = [ $result['message'] ];
        foreach ( $result['items'] as $index => $item ) {
            $status  = ! empty( $item['success'] ) ? 'PASS' : ( 'skipped' === (string) ( $item['status'] ?? '' ) ? 'SKIP' : 'FAIL' );
            $lines[] = sprintf( '[%d] %s — %s: %s', $index + 1, $status, (string) ( $item['label'] ?? '' ), (string) ( $item['message'] ?? '' ) );
        }
        return implode( "\n", $lines );
    }

    private static function is_mutation_type( string $type ): bool {
        return in_array( $type, [ 'setup_action', 'feature_toggle', 'config_mutation', 'ajax_request' ], true );
    }
}
