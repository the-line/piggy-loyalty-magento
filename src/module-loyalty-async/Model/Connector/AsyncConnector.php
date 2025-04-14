<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Connector;

use Leat\Loyalty\Model\Connector;

/**
 * AsyncConnector is an adapter that makes the Leat Connector compatible with AsyncQueue
 */
class AsyncConnector extends Connector
{
    protected const string LOG_FILE = "leat/loyalty_async_connector.log";
    protected const string DEBUG_LOG_FILE = "leat/loyalty_async_connector_debug.log";
    protected const string ERROR_MAIL_LAST_SENT = "leat_loyalty_async_connector_error_last_sent";
    protected const string ERROR_EMAIL_TEMPLATE = 'async_queue_error_mail';
}
