<?php
/**
 * MYOB Integration – Admin Settings Template
 * Top-tab layout, full-width form labels, dual log tabs.
 */

$woo_settings          = get_option( 'woocommerce_MYOB_integrations_settings', array() );
$company_file_id       = $woo_settings['WC_MYOB_company_file_id'] ?? '';
$company_file_username = $woo_settings['WC_MYOB_company_file_username'] ?? '';
$unauthorized_count    = (int) get_option( 'WC_MYOB_api_unauthorized_count', 0 );
$refresh_token_failed  = get_option( 'WC_MYOB_refresh_token_failed' );

$is_connected = ! empty( $company_file_username )
    && ! empty( $company_file_id )
    && false !== $company_file_id
    && 'yes' !== $refresh_token_failed
    && 0 === $unauthorized_count;

// Restore last active panel from session (via hidden input written by JS)
// Default: connection if not connected, config if connected
$default_panel = $is_connected ? 'panel-config' : 'panel-connection';

// wp_kses allowed tags
$allowed = wp_kses_allowed_html( 'post' );
$allowed['input']  = array( 'class'=>array(),'id'=>array(),'name'=>array(),'value'=>array(),'type'=>array(),'onclick'=>array(),'style'=>array(),'checked'=>array(),'placeholder'=>array(),'min'=>array(),'max'=>array(),'step'=>array(),'disabled'=>array(),'required'=>array() );
$allowed['button'] = array( 'class'=>array(),'id'=>array(),'name'=>array(),'value'=>array(),'type'=>array(),'onclick'=>array(),'onfocus'=>array(),'onblur'=>array(),'disabled'=>array(),'style'=>array(),'aria-label'=>array(),'aria-expanded'=>array(),'aria-controls'=>array(),'data-tip'=>array(),'data-panel'=>array(),'data-info-id'=>array(),'role'=>array() );
$allowed['select'] = array( 'class'=>array(),'id'=>array(),'name'=>array(),'style'=>array(),'disabled'=>array() );
$allowed['option'] = array( 'selected'=>array(),'value'=>array(),'class'=>array(),'disabled'=>array() );
$allowed['code']   = array();
$allowed['strong'] = array();
?>

<!-- ── Top tab navigation ─────────────────────────────────── -->
<div class="opmc-tab-nav" role="tablist">
    <button type="button" class="opmc-tab-btn" id="opmc-tab-connection"
            data-panel="panel-connection" role="tab" aria-controls="panel-connection">
        <span class="dashicons dashicons-admin-plugins"></span> Connection
    </button>
    <?php if ( $is_connected ) : ?>
    <button type="button" class="opmc-tab-btn" id="opmc-tab-config"
            data-panel="panel-config" role="tab" aria-controls="panel-config">
        <span class="dashicons dashicons-admin-settings"></span> Configuration
    </button>
    <button type="button" class="opmc-tab-btn" id="opmc-tab-sync-log"
            data-panel="panel-sync-log" role="tab" aria-controls="panel-sync-log">
        <span class="dashicons dashicons-update"></span> Sync Log
    </button>
    <button type="button" class="opmc-tab-btn" id="opmc-tab-debug-log"
            data-panel="panel-debug-log" role="tab" aria-controls="panel-debug-log">
        <span class="dashicons dashicons-text-page"></span> Debug Log
    </button>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════
     PANEL 1 – CONNECTION
     ══════════════════════════════════════════════════════════ -->
<div id="panel-connection" class="opmc-panel" role="tabpanel" aria-labelledby="opmc-tab-connection">
    <div class="opmc-panel-header">
        <div>
            <h2><span class="dashicons dashicons-admin-plugins"></span> Connection Settings</h2>
            <p>Connect this site to your MYOB AccountRight company file.</p>
        </div>
        <?php if ( $is_connected ) : ?>
            <span class="opmc-conn-badge connected"><span class="dot"></span> Connected &mdash; <?php echo esc_html( $company_file_username ); ?></span>
        <?php else : ?>
            <span class="opmc-conn-badge disconnected"><span class="dot"></span> Not Connected</span>
        <?php endif; ?>
    </div>
    <div class="opmc-panel-body">
        <p class="status_of_conn"></p>
        <table class="form-table">
            <?php
            foreach ( $first_tab_fields as $sk => $sv ) {
                $type = $this->get_field_type( $sv );
                $html = method_exists( $this, "generate_{$type}_html" )
                    ? $this->{"generate_{$type}_html"}( $sk, $sv )
                    : $this->generate_text_html( $sk, $sv );
                echo wp_kses( $html, $allowed );
            }
            ?>
        </table>
    </div>
</div>

<?php if ( $is_connected ) : ?>

<!-- ══════════════════════════════════════════════════════════
     PANEL 2 – CONFIGURATION
     ══════════════════════════════════════════════════════════ -->
<div id="panel-config" class="opmc-panel" role="tabpanel" aria-labelledby="opmc-tab-config">
    <div class="opmc-panel-header">
        <div>
            <h2><span class="dashicons dashicons-admin-settings"></span> Configuration</h2>
            <p>Accounts, invoices, products, customers, pricing and sync behaviour.</p>
        </div>
    </div>
    <div class="opmc-panel-body">
        <table class="form-table">
            <?php
            foreach ( $second_tab_fields as $sk => $sv ) {
                $type = $this->get_field_type( $sv );
                $html = method_exists( $this, "generate_{$type}_html" )
                    ? $this->{"generate_{$type}_html"}( $sk, $sv )
                    : $this->generate_text_html( $sk, $sv );
                echo wp_kses( $html, $allowed );
            }
            ?>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     PANEL 3 – SYNC LOG
     ══════════════════════════════════════════════════════════ -->
<div id="panel-sync-log" class="opmc-panel" role="tabpanel" aria-labelledby="opmc-tab-sync-log">
    <div class="opmc-panel-header">
        <div>
            <h2><span class="dashicons dashicons-update"></span> Sync Log</h2>
            <p>Product and customer sync events written directly by this plugin.</p>
        </div>
        <button type="button" class="button" id="opmc-sync-log-refresh">
            &#8635; Refresh
        </button>
    </div>
    <div class="opmc-panel-body">
        <!-- Retention countdown bar -->
        <div class="opmc-log-retention-bar" id="opmc-retention-bar" style="display:none;">
            <span class="opmc-retention-icon dashicons dashicons-clock"></span>
            <span id="opmc-retention-label"></span>
            <span class="opmc-retention-countdown" id="opmc-retention-countdown"></span>
        </div>
        <div class="opmc-log-toolbar">
            <button type="button" class="button button-small" id="opmc-sync-log-clear">Clear Log</button>
            <span class="opmc-log-meta" id="opmc-sync-log-count"></span>
        </div>
        <div class="opmc-log-table-wrap">
            <table class="opmc-log-table">
                <thead>
                    <tr>
                        <th style="width:170px">Date / Time (UTC)</th>
                        <th style="width:90px">Level</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody id="opmc-sync-log-body">
                    <tr><td colspan="3" class="opmc-log-empty">Loading&hellip;</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     PANEL 4 – DEBUG LOG
     ══════════════════════════════════════════════════════════ -->
<div id="panel-debug-log" class="opmc-panel" role="tabpanel" aria-labelledby="opmc-tab-debug-log">
    <div class="opmc-panel-header">
        <div>
            <h2><span class="dashicons dashicons-text-page"></span> Debug Log</h2>
            <p>WooCommerce log entries — only written when &ldquo;Enable Debug Logging&rdquo; is on.</p>
        </div>
    </div>
    <div class="opmc-panel-body">
        <?php
        $myob_log_files = array();
        $wc_log_files   = WC_Log_Handler_File::get_log_files();
        foreach ( $wc_log_files as $lf ) {
            $parts = explode( '-', $lf );
            if ( isset( $parts[0] ) && 'myob' === strtolower( $parts[0] ) ) {
                $date_label = isset( $parts[2], $parts[3], $parts[4] )
                    ? $parts[2] . '-' . $parts[3] . '-' . rtrim( $parts[4], '.log' )
                    : $lf;
                $myob_log_files[] = array( 'label' => $date_label, 'file' => $lf );
            }
        }

        if ( ! empty( $myob_log_files ) ) :
            $selected_file = get_option( 'selected_opmc_myob_log_view_date', '' );
            $selected_text = get_option( 'selected_opmc_myob_log_view_text', gmdate( 'Y-m-d' ) );
        ?>
        <div class="opmc-log-toolbar">
            <select id="logs" name="logs" style="min-width:180px;">
                <?php foreach ( $myob_log_files as $lf ) : ?>
                    <option value="<?php echo esc_attr( $lf['file'] ); ?>"
                        <?php selected( $lf['label'], $selected_text ); ?>>
                        <?php echo esc_html( $lf['label'] ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="button" id="view_log">View</button>
        </div>
        <?php
            $log_path = WC_LOG_DIR . $selected_file;
            if ( $selected_file && file_exists( $log_path ) ) :
                $raw_log   = file_get_contents( $log_path );
                $log_lines = array_values( array_filter( array_reverse( explode( PHP_EOL, $raw_log ) ), 'strlen' ) );
        ?>
        <div class="opmc-log-table-wrap">
            <table class="opmc-log-table">
                <thead>
                    <tr>
                        <th style="width:190px">Date</th>
                        <th style="width:130px">Process</th>
                        <th style="width:90px">Status</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $log_lines ) ) : ?>
                    <tr><td colspan="4" class="opmc-log-empty">No log entries found.</td></tr>
                <?php else : ?>
                    <?php foreach ( $log_lines as $entry ) :
                        $parts = preg_split( '/ - | NOTICE /', $entry, 2 );
                        if ( strpos( $parts[0], '+00:00' ) !== false ) {
                            $dt       = explode( '+00:00', $parts[0] );
                            $log_date = rtrim( str_replace( 'T', ' ', $dt[0] ) );
                        } else {
                            $log_date = rtrim( str_replace( '@', '', $parts[0] ), ' -' );
                        }
                        $msg_arr = isset( $parts[1] ) ? explodeLogMessage( $parts[1] ) : '';
                        if ( ! is_array( $msg_arr ) ) continue;
                    ?>
                    <tr>
                        <td><?php echo esc_html( $log_date ); ?></td>
                        <td><?php echo esc_html( $msg_arr[0] ?? '' ); ?></td>
                        <td><?php echo esc_html( $msg_arr[1] ?? '' ); ?></td>
                        <td><?php echo esc_html( $msg_arr[2] ?? '' ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php else : ?>
            <p class="opmc-log-empty">No debug log files found. Enable &ldquo;Debug Logging&rdquo; in Configuration and trigger a sync.</p>
        <?php endif; ?>
    </div>
</div>

<?php endif; // is_connected ?>

<script>
(function ($) {
    'use strict';

    var DEFAULT_PANEL = '<?php echo esc_js( $default_panel ); ?>';
    var $saveBtn = jQuery('button.button-primary.woocommerce-save-button');
    var $submitP = jQuery('p.submit');

    // ── Activate a panel ────────────────────────────────────
    function activatePanel(id) {
        jQuery('.opmc-panel').removeClass('active');
        jQuery('.opmc-tab-btn').removeClass('active').attr('aria-selected', 'false');

        jQuery('#' + id).addClass('active');
        jQuery('[data-panel="' + id + '"]').addClass('active').attr('aria-selected', 'true');

        var isConfig = (id === 'panel-config');
        $saveBtn.css('display', isConfig ? '' : 'none');
        if (isConfig) {
            $submitP.css({ border: '1px solid #c3c4c7', 'border-top': 'none', padding: '12px 24px', margin: '0' });
        } else {
            $submitP.css({ border: '', 'border-top': '', padding: '', margin: '' });
        }

        sessionStorage.setItem('opmc_myob_panel', id);

        if (id === 'panel-sync-log') {
            loadSyncLog();
        }
    }

    // ── Info panel toggle (replaces tipTip tooltip) ──────────
    jQuery(document).on('click', '.opmc-info-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $btn    = jQuery(this);
        var tipText = $btn.attr('data-tip') || '';
        var $row    = $btn.closest('tr');
        var $next   = $row.next('.opmc-info-row');
        var isOpen  = $btn.attr('aria-expanded') === 'true';

        // Close all open panels first
        jQuery('.opmc-info-btn[aria-expanded="true"]').not($btn).each(function () {
            jQuery(this).attr('aria-expanded', 'false');
            jQuery(this).closest('tr').next('.opmc-info-row').remove();
        });

        if (isOpen) {
            $btn.attr('aria-expanded', 'false');
            $next.remove();
        } else {
            $btn.attr('aria-expanded', 'true');
            // .attr() already decodes HTML entities from data-tip — use directly
            var $infoRow = jQuery(
                '<tr class="opmc-info-row">' +
                '<td colspan="2">' +
                '<div class="opmc-info-row-inner">' +
                '<div class="opmc-info-row-text"></div>' +
                '</div>' +
                '</td>' +
                '</tr>'
            );
            // Set text safely using .text() to avoid XSS
            $infoRow.find('.opmc-info-row-text').text(tipText);
            $row.after($infoRow);
        }
    });

    // Close info panel when clicking outside
    jQuery(document).on('click', function (e) {
        if (!jQuery(e.target).closest('.opmc-info-btn, .opmc-info-row').length) {
            jQuery('.opmc-info-btn[aria-expanded="true"]').each(function () {
                jQuery(this).attr('aria-expanded', 'false');
                jQuery(this).closest('tr').next('.opmc-info-row').remove();
            });
        }
    });

    // ── Tab click ────────────────────────────────────────────
    jQuery('.opmc-tab-btn').on('click', function () {
        activatePanel(jQuery(this).data('panel'));
    });

    // ── Restore session or use default ───────────────────────
    var stored = sessionStorage.getItem('opmc_myob_panel');
    activatePanel((stored && jQuery('#' + stored).length) ? stored : DEFAULT_PANEL);

    // ── Debug log: view button ───────────────────────────────
    jQuery('#view_log').on('click', function (e) {
        e.preventDefault();
        var $sel = jQuery('#logs option:selected');
        jQuery.ajax({
            url: OpmcMyobScriptAjax.ajaxurl,
            type: 'post',
            dataType: 'json',
            data: {
                action:    'opmc_myob_view_debug_logs',
                selected:  $sel.val(),
                selected2: $sel.text().trim(),
                security:  OpmcMyobScriptAjax.ajax_nonce
            },
            success: function (data) {
                if (data && data.result === 'success') {
                    sessionStorage.setItem('opmc_myob_panel', 'panel-debug-log');
                    location.reload();
                }
            }
        });
    });

    // ── Sync log helpers ─────────────────────────────────────
    function badge(level) {
        var map = { INFO: 'info', SUCCESS: 'success', WARNING: 'warning', ERROR: 'error' };
        var c = map[level] || 'info';
        return '<span class="opmc-badge opmc-badge-' + c + '">' + level + '</span>';
    }

    function escHtml(s) {
        return jQuery('<div>').text(s == null ? '' : String(s)).html();
    }

    var countdownTimer = null;

    function startCountdown(nextPurgeUtc, retainDays) {
        clearInterval(countdownTimer);

        if (!nextPurgeUtc) {
            jQuery('#opmc-retention-bar').hide();
            return;
        }

        var $bar       = jQuery('#opmc-retention-bar');
        var $label     = jQuery('#opmc-retention-label');
        var $countdown = jQuery('#opmc-retention-countdown');
        var purgeMs    = new Date(nextPurgeUtc.replace(' ', 'T') + 'Z').getTime();

        $label.text('Retention: ' + retainDays + ' day' + (retainDays === 1 ? '' : 's') + ' — next purge in');
        $bar.show();

        function tick() {
            var nowMs = Date.now();
            var diff  = Math.max(0, Math.floor((purgeMs - nowMs) / 1000));

            if (diff <= 0) {
                $countdown.text('Purging soon…');
                clearInterval(countdownTimer);
                return;
            }

            var d = Math.floor(diff / 86400);
            var h = Math.floor((diff % 86400) / 3600);
            var m = Math.floor((diff % 3600) / 60);
            var s = diff % 60;

            var parts = [];
            if (d > 0) parts.push(d + 'd');
            if (h > 0) parts.push(h + 'h');
            if (m > 0) parts.push(m + 'm');
            parts.push(s + 's');

            $countdown.text(parts.join(' '));
        }

        tick();
        countdownTimer = setInterval(tick, 1000);
    }

    function loadSyncLog() {
        var $body = jQuery('#opmc-sync-log-body');
        $body.html('<tr><td colspan="3" class="opmc-log-empty">Loading&hellip;</td></tr>');
        jQuery.ajax({
            url: OpmcMyobScriptAjax.ajaxurl,
            type: 'post',
            dataType: 'json',
            data: { action: 'opmc_myob_get_sync_log', security: OpmcMyobScriptAjax.ajax_nonce },
            success: function (resp) {
                if (!resp.success || !resp.lines || !resp.lines.length) {
                    $body.html('<tr><td colspan="3" class="opmc-log-empty">No sync events recorded yet.</td></tr>');
                    jQuery('#opmc-sync-log-count').text('');
                    jQuery('#opmc-retention-bar').hide();
                    clearInterval(countdownTimer);
                    return;
                }

                var html = '';
                jQuery.each(resp.lines, function (i, line) {
                    var m = line.match(/^\[([^\]]+)\]\s*\[([A-Z]+)\]\s*(.*)/);
                    if (m) {
                        html += '<tr>'
                            + '<td style="white-space:nowrap">' + escHtml(m[1]) + '</td>'
                            + '<td>' + badge(m[2]) + '</td>'
                            + '<td>' + escHtml(m[3]) + '</td>'
                            + '</tr>';
                    } else {
                        html += '<tr><td colspan="3">' + escHtml(line) + '</td></tr>';
                    }
                });
                $body.html(html);
                jQuery('#opmc-sync-log-count').text(resp.lines.length + ' entries (newest first)');

                // Start countdown if purge info is available
                startCountdown(resp.next_purge_utc || null, resp.retain_days || 7);
            },
            error: function () {
                $body.html('<tr><td colspan="3" class="opmc-log-empty">Could not load sync log.</td></tr>');
            }
        });
    }

    jQuery('#opmc-sync-log-refresh').on('click', loadSyncLog);

    jQuery('#opmc-sync-log-clear').on('click', function () {
        if (!confirm('Clear all sync log entries? This cannot be undone.')) return;
        jQuery.ajax({
            url: OpmcMyobScriptAjax.ajaxurl,
            type: 'post',
            dataType: 'json',
            data: { action: 'opmc_myob_clear_sync_log', security: OpmcMyobScriptAjax.ajax_nonce },
            success: function () {
                clearInterval(countdownTimer);
                jQuery('#opmc-retention-bar').hide();
                loadSyncLog();
                jQuery('#opmc-sync-log-count').text('');
            }
        });
    });

}(jQuery));
</script>
