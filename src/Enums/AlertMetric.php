<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Enums;

enum AlertMetric: string
{
    case RAM_PERCENT = 'ram_percent';
    case CPU_PERCENT = 'cpu_percent';
    case DISK_PERCENT = 'disk_percent';
    case SERVER_OFFLINE = 'server_offline';
    case SERVER_CRASHED = 'server_crashed';
    case NODE_OFFLINE = 'node_offline';
    case BACKUP_FAILED = 'backup_failed';
    // New metrics (Phase 2/3)
    case NETWORK_IN = 'network_in';
    case NETWORK_OUT = 'network_out';
    case SWAP_PERCENT = 'swap_percent';
    case PROCESS_COUNT = 'process_count';
    case INODE_PERCENT = 'inode_percent';
    case DISK_IOPS = 'disk_iops';
    case BACKUP_DURATION = 'backup_duration';
    case BACKUP_STALE = 'backup_stale';
    case OOM_EVENTS = 'oom_events';
    case SSL_CERT_EXPIRY = 'ssl_cert_expiry';
    case WINGS_VERSION = 'wings_version';
    case QUEUE_FAILED_JOBS = 'queue_failed_jobs';
    case QUEUE_OLDEST_JOB_AGE = 'queue_oldest_job_age';
    case CUSTOM = 'custom';

    public function isBoolean(): bool
    {
        return match ($this) {
            self::SERVER_OFFLINE,
            self::SERVER_CRASHED,
            self::NODE_OFFLINE,
            self::BACKUP_FAILED,
            self::BACKUP_STALE,
            self::OOM_EVENTS,
            self::WINGS_VERSION, => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::RAM_PERCENT => trans('resourceusagealerts::strings.metrics.ram_percent'),
            self::CPU_PERCENT => trans('resourceusagealerts::strings.metrics.cpu_percent'),
            self::DISK_PERCENT => trans('resourceusagealerts::strings.metrics.disk_percent'),
            self::SERVER_OFFLINE => trans('resourceusagealerts::strings.metrics.server_offline'),
            self::SERVER_CRASHED => trans('resourceusagealerts::strings.metrics.server_crashed'),
            self::NODE_OFFLINE => trans('resourceusagealerts::strings.metrics.node_offline'),
            self::BACKUP_FAILED => trans('resourceusagealerts::strings.metrics.backup_failed'),
            self::NETWORK_IN => trans('resourceusagealerts::strings.metrics.network_in'),
            self::NETWORK_OUT => trans('resourceusagealerts::strings.metrics.network_out'),
            self::SWAP_PERCENT => trans('resourceusagealerts::strings.metrics.swap_percent'),
            self::PROCESS_COUNT => trans('resourceusagealerts::strings.metrics.process_count'),
            self::INODE_PERCENT => trans('resourceusagealerts::strings.metrics.inode_percent'),
            self::DISK_IOPS => trans('resourceusagealerts::strings.metrics.disk_iops'),
            self::BACKUP_DURATION => trans('resourceusagealerts::strings.metrics.backup_duration'),
            self::BACKUP_STALE => trans('resourceusagealerts::strings.metrics.backup_stale'),
            self::OOM_EVENTS => trans('resourceusagealerts::strings.metrics.oom_events'),
            self::SSL_CERT_EXPIRY => trans('resourceusagealerts::strings.metrics.ssl_cert_expiry'),
            self::WINGS_VERSION => trans('resourceusagealerts::strings.metrics.wings_version'),
            self::QUEUE_FAILED_JOBS => trans('resourceusagealerts::strings.metrics.queue_failed_jobs'),
            self::QUEUE_OLDEST_JOB_AGE => trans('resourceusagealerts::strings.metrics.queue_oldest_job_age'),
            self::CUSTOM => trans('resourceusagealerts::strings.metrics.custom'),
        };
    }
}
