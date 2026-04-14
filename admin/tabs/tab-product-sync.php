<?php
/**
 * Product Sync tab content.
 *
 * @var object $pulseem_admin_model
 * @var array  $options
 */
if (!defined('ABSPATH')) {
    exit;
}

$pulseem_product_count = class_exists( 'PulseemProductSync' ) ? PulseemProductSync::get_total_count() : 0;
$pulseem_chunking      = ! empty( $options['product_sync_chunking_enabled'] );
$pulseem_chunk_size    = isset( $options['product_sync_chunk_size'] ) ? (int) $options['product_sync_chunk_size'] : 10000;
if ( $pulseem_chunk_size < 100 ) {
    $pulseem_chunk_size = 10000;
}
$pulseem_xml_enabled   = ! empty( $options['product_sync_xml_enabled'] );
$pulseem_rest_base     = rest_url( 'pulseem/v1/get-products-json' );
$pulseem_zip_base      = rest_url( 'pulseem/v1/get-products-zip' );
$pulseem_has_zip_ext   = class_exists( 'ZipArchive' );
$pulseem_auto_open     = $pulseem_product_count > 20000;
$pulseem_feed_token    = class_exists( 'PulseemProductSync' ) ? PulseemProductSync::get_or_create_token() : '';
?>
<div x-show="activeTab === 'product_sync'" class="bg-white shadow-sm rounded-lg border">
    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
        <h2 class="text-xl font-semibold text-gray-800"><?php esc_html_e('Product Synchronization', 'pulseem'); ?></h2>
    </div>
    <div class="p-6 space-y-6"
         x-data='{
            productCount: <?php echo (int) $pulseem_product_count; ?>,
            chunkingEnabled: <?php echo $pulseem_chunking ? 'true' : 'false'; ?>,
            chunkSize: <?php echo (int) $pulseem_chunk_size; ?>,
            xmlEnabled: <?php echo $pulseem_xml_enabled ? 'true' : 'false'; ?>,
            advancedOpen: <?php echo $pulseem_auto_open ? 'true' : 'false'; ?>,
            baseUrl: <?php echo wp_json_encode( $pulseem_rest_base ); ?>,
            zipBase: <?php echo wp_json_encode( $pulseem_zip_base ); ?>,
            hasZipExt: <?php echo $pulseem_has_zip_ext ? 'true' : 'false'; ?>,
            deliveryMode: "view",
            token: <?php echo wp_json_encode( $pulseem_feed_token ); ?>,
            tokenCopied: false,
            regenerating: false,
            get effectiveSize() { return Math.max(100, parseInt(this.chunkSize) || 10000); },
            get totalChunks() { return Math.max(1, Math.ceil(this.productCount / this.effectiveSize)); },
            chunkRange(i) {
                const start = i * this.effectiveSize + 1;
                const end = Math.min((i + 1) * this.effectiveSize, this.productCount);
                return start + "-" + end;
            },
            _appendToken(url) {
                if (!this.token) return url;
                const sep = url.indexOf("?") === -1 ? "?" : "&";
                return url + sep + "token=" + encodeURIComponent(this.token);
            },
            _applyDelivery(url) {
                if (this.deliveryMode !== "download") return url;
                const sep = url.indexOf("?") === -1 ? "?" : "&";
                return url + sep + "download=1";
            },
            chunkUrl(i, format) {
                const sep = this.baseUrl.indexOf("?") === -1 ? "?" : "&";
                let url = this.baseUrl + sep + "chunk=" + i + "&chunk_size=" + this.effectiveSize;
                if (format === "xml") url += "&format=xml";
                return this._appendToken(this._applyDelivery(url));
            },
            fullUrl(format) {
                const sep = this.baseUrl.indexOf("?") === -1 ? "?" : "&";
                const url = format === "xml" ? this.baseUrl + sep + "format=xml" : this.baseUrl;
                return this._appendToken(this._applyDelivery(url));
            },
            zipUrl(format) {
                const sep = this.zipBase.indexOf("?") === -1 ? "?" : "&";
                let url = this.zipBase + sep + "format=" + format + "&chunk_size=" + this.effectiveSize;
                return this._appendToken(url);
            },
            copyToken() {
                if (!this.token) return;
                navigator.clipboard.writeText(this.token).then(() => {
                    this.tokenCopied = true;
                    setTimeout(() => { this.tokenCopied = false; }, 1500);
                });
            },
            regenerateToken() {
                if (this.regenerating) return;
                if (!confirm(<?php echo wp_json_encode( __( 'Regenerate token? Existing URLs using the old token will stop working.', 'pulseem' ) ); ?>)) return;
                this.regenerating = true;
                const form = new FormData();
                form.append("action", "pulseem_regenerate_product_sync_token");
                form.append("nonce", (window.pulseem_ajax && window.pulseem_ajax.nonce) || "");
                fetch((window.pulseem_ajax && window.pulseem_ajax.ajax_url) || "/wp-admin/admin-ajax.php", {
                    method: "POST", credentials: "same-origin", body: form
                }).then(r => r.json()).then(res => {
                    if (res && res.success && res.data && res.data.token) {
                        this.token = res.data.token;
                    } else {
                        alert(<?php echo wp_json_encode( __( 'Failed to regenerate token.', 'pulseem' ) ); ?>);
                    }
                }).catch(() => {
                    alert(<?php echo wp_json_encode( __( 'Failed to regenerate token.', 'pulseem' ) ); ?>);
                }).finally(() => { this.regenerating = false; });
            }
         }'>
        <!-- Enable Product Sync -->
        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div class="flex items-center">
                <label for="is_product_sync_switch" class="block text-sm font-medium text-gray-900">
                    <?php esc_html_e('Enable Product Synchronization', 'pulseem'); ?>
                </label>
            </div>
            <div class="flex items-center">
                <label class="switch">
                    <input type="hidden" name="pulseem_settings[is_product_sync]" value="0">
                    <input
                        type="checkbox"
                        id="is_product_sync_switch"
                        name="pulseem_settings[is_product_sync]"
                        value="1"
                        <?php checked(1, isset($options['is_product_sync']) ? $options['is_product_sync'] : 0); ?>
                    >
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <!-- Advanced Settings (chunked export) -->
        <div class="border rounded-lg bg-white">
            <button type="button"
                    class="w-full flex items-center justify-between p-4 border-b bg-gray-50 rounded-t-lg text-start"
                    @click="advancedOpen = !advancedOpen">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-semibold text-gray-800">
                        <?php esc_html_e('Advanced Settings', 'pulseem'); ?>
                    </span>
                    <span class="text-xs text-gray-500">
                        <?php
                        printf(
                            /* translators: %s: total product count including variations */
                            esc_html__( '%s products in store (including variations)', 'pulseem' ),
                            '<span x-text="productCount.toLocaleString()"></span>'
                        );
                        ?>
                    </span>
                </div>
                <svg class="h-5 w-5 text-gray-500 transition-transform"
                     :class="advancedOpen ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="advancedOpen" x-cloak class="p-4 space-y-4">
                <!-- Warning banner for large catalogs -->
                <div x-show="productCount > 20000"
                     class="p-4 bg-yellow-50 border-s-4 border-yellow-400 rounded-e-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.518 11.59c.75 1.334-.213 2.98-1.743 2.98H3.482c-1.53 0-2.493-1.646-1.743-2.98L8.257 3.099zM11 13a1 1 0 10-2 0 1 1 0 002 0zm-1-8a1 1 0 00-1 1v3a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ms-3">
                            <h3 class="text-sm font-medium text-yellow-800">
                                <?php esc_html_e( 'Large product catalog detected', 'pulseem' ); ?>
                            </h3>
                            <p class="text-sm text-yellow-700 mt-1">
                                <?php esc_html_e( 'Your store has over 20,000 products (including variations). It is recommended to enable chunked export below to split the feed into multiple files and avoid timeouts or memory issues.', 'pulseem' ); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Access token -->
                <div class="border rounded-lg bg-white">
                    <div class="p-4 border-b bg-gray-50">
                        <span class="text-sm font-semibold text-gray-800">
                            <?php esc_html_e( 'Feed access token', 'pulseem' ); ?>
                        </span>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php esc_html_e( 'The feed endpoint is private. Include this token as a ?token=... query parameter (already appended to the URLs below) so external services like Pulseem can fetch the feed without logging in. Keep it secret.', 'pulseem' ); ?>
                        </p>
                    </div>
                    <div class="p-3 flex items-center gap-2">
                        <input type="text" readonly
                               :value="token"
                               class="flex-1 font-mono text-xs rounded-md border-gray-300 bg-gray-50"
                               @focus="$event.target.select()">
                        <button type="button" @click="copyToken()"
                                class="inline-flex items-center px-3 py-1.5 text-xs rounded-md text-pink-700 bg-pink-50 hover:bg-pink-100">
                            <span x-show="!tokenCopied"><?php esc_html_e( 'Copy', 'pulseem' ); ?></span>
                            <span x-show="tokenCopied"><?php esc_html_e( 'Copied!', 'pulseem' ); ?></span>
                        </button>
                        <button type="button" @click="regenerateToken()" :disabled="regenerating"
                                class="inline-flex items-center px-3 py-1.5 text-xs rounded-md text-gray-700 bg-gray-100 hover:bg-gray-200 disabled:opacity-50">
                            <span x-show="!regenerating"><?php esc_html_e( 'Regenerate', 'pulseem' ); ?></span>
                            <span x-show="regenerating"><?php esc_html_e( 'Regenerating...', 'pulseem' ); ?></span>
                        </button>
                    </div>
                </div>

                <!-- Chunking toggle -->
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <label for="product_sync_chunking_enabled_switch" class="block text-sm font-medium text-gray-900">
                        <?php esc_html_e( 'Split export into multiple files', 'pulseem' ); ?>
                    </label>
                    <label class="switch">
                        <input type="hidden" name="pulseem_settings[product_sync_chunking_enabled]" value="0">
                        <input type="checkbox"
                               id="product_sync_chunking_enabled_switch"
                               name="pulseem_settings[product_sync_chunking_enabled]"
                               value="1"
                               x-model="chunkingEnabled">
                        <span class="slider"></span>
                    </label>
                </div>

                <!-- Chunk size input -->
                <div x-show="chunkingEnabled" class="p-4 bg-gray-50 rounded-lg">
                    <label for="product_sync_chunk_size" class="block text-sm font-medium text-gray-900 mb-2">
                        <?php esc_html_e( 'Products per file', 'pulseem' ); ?>
                    </label>
                    <input type="number"
                           id="product_sync_chunk_size"
                           name="pulseem_settings[product_sync_chunk_size]"
                           min="100"
                           max="100000"
                           step="1000"
                           x-model.number="chunkSize"
                           class="block w-48 rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm">
                    <p class="text-xs text-gray-500 mt-1">
                        <?php esc_html_e( 'Default: 10,000. Variations count as products.', 'pulseem' ); ?>
                    </p>
                </div>

                <!-- XML toggle -->
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <label for="product_sync_xml_enabled_switch" class="block text-sm font-medium text-gray-900">
                        <?php esc_html_e( 'Also expose XML format', 'pulseem' ); ?>
                    </label>
                    <label class="switch">
                        <input type="hidden" name="pulseem_settings[product_sync_xml_enabled]" value="0">
                        <input type="checkbox"
                               id="product_sync_xml_enabled_switch"
                               name="pulseem_settings[product_sync_xml_enabled]"
                               value="1"
                               x-model="xmlEnabled">
                        <span class="slider"></span>
                    </label>
                </div>

                <!-- Delivery mode toggle -->
                <div class="p-3 bg-gray-50 rounded-lg flex items-center gap-4 flex-wrap">
                    <span class="text-sm font-medium text-gray-700">
                        <?php esc_html_e( 'When clicking a link:', 'pulseem' ); ?>
                    </span>
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="radio" name="pulseem_delivery_mode" value="view" x-model="deliveryMode"
                               class="text-pink-600 focus:ring-pink-500">
                        <?php esc_html_e( 'Open in browser', 'pulseem' ); ?>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="radio" name="pulseem_delivery_mode" value="download" x-model="deliveryMode"
                               class="text-pink-600 focus:ring-pink-500">
                        <?php esc_html_e( 'Download file', 'pulseem' ); ?>
                    </label>
                </div>

                <!-- Chunk URL list -->
                <div x-show="chunkingEnabled" class="border rounded-lg bg-white">
                    <div class="p-4 border-b bg-gray-50 flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-800">
                            <?php esc_html_e( 'Chunked feed', 'pulseem' ); ?>
                        </span>
                        <span class="text-xs text-gray-500"
                              x-text="totalChunks + ' ' + (totalChunks === 1 ? 'file' : 'files')"></span>
                    </div>
                    <ul class="divide-y">
                        <template x-for="i in totalChunks" :key="i">
                            <li class="p-3 flex items-center gap-4 text-sm">
                                <span class="w-32 text-gray-600">
                                    <?php esc_html_e( 'Chunk', 'pulseem' ); ?>
                                    <span x-text="i"></span> / <span x-text="totalChunks"></span>
                                </span>
                                <span class="text-xs text-gray-400" x-text="'(' + chunkRange(i-1) + ')'"></span>
                                <a :href="chunkUrl(i-1, 'json')" :target="deliveryMode === 'view' ? '_blank' : '_self'"
                                   class="ms-auto inline-flex items-center gap-1 px-3 py-1 text-xs rounded-md text-pink-700 bg-pink-50 hover:bg-pink-100">
                                    <svg x-show="deliveryMode === 'download'" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                    JSON
                                </a>
                                <a x-show="xmlEnabled" :href="chunkUrl(i-1, 'xml')" :target="deliveryMode === 'view' ? '_blank' : '_self'"
                                   class="inline-flex items-center gap-1 px-3 py-1 text-xs rounded-md text-pink-700 bg-pink-50 hover:bg-pink-100">
                                    <svg x-show="deliveryMode === 'download'" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                    XML
                                </a>
                            </li>
                        </template>
                    </ul>

                    <!-- Download all as ZIP -->
                    <div class="p-3 border-t bg-gray-50 flex items-center gap-3 flex-wrap">
                        <span class="text-xs text-gray-600">
                            <?php esc_html_e( 'Download all chunks as a single ZIP:', 'pulseem' ); ?>
                        </span>
                        <template x-if="hasZipExt">
                            <div class="flex items-center gap-2">
                                <a :href="zipUrl('json')"
                                   class="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium rounded-md text-white bg-gradient-to-r from-pink-600 to-pink-500 hover:from-pink-700 hover:to-pink-600">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                    <?php esc_html_e( 'ZIP (JSON)', 'pulseem' ); ?>
                                </a>
                                <a x-show="xmlEnabled" :href="zipUrl('xml')"
                                   class="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium rounded-md text-white bg-gradient-to-r from-pink-600 to-pink-500 hover:from-pink-700 hover:to-pink-600">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                    <?php esc_html_e( 'ZIP (XML)', 'pulseem' ); ?>
                                </a>
                            </div>
                        </template>
                        <span x-show="!hasZipExt" class="text-xs text-gray-500 italic">
                            <?php esc_html_e( 'ZIP download unavailable — the PHP zip extension is not installed on this server.', 'pulseem' ); ?>
                        </span>
                    </div>
                </div>

                <!-- Full feed links -->
                <div class="border rounded-lg bg-white">
                    <div class="p-4 border-b bg-gray-50">
                        <span class="text-sm font-semibold text-gray-800">
                            <?php esc_html_e( 'Full feed (all products in one file)', 'pulseem' ); ?>
                        </span>
                        <p class="text-xs text-gray-500 mt-1" x-show="productCount > 20000">
                            <?php esc_html_e( 'Warning: with a large catalog this single-file response may be slow or hit memory limits. Prefer chunked or ZIP above.', 'pulseem' ); ?>
                        </p>
                    </div>
                    <div class="p-3 flex items-center gap-3 text-sm">
                        <a :href="fullUrl('json')" :target="deliveryMode === 'view' ? '_blank' : '_self'"
                           class="inline-flex items-center gap-1 px-3 py-1 text-xs rounded-md text-pink-700 bg-pink-50 hover:bg-pink-100">
                            <svg x-show="deliveryMode === 'download'" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                            JSON
                        </a>
                        <a x-show="xmlEnabled" :href="fullUrl('xml')" :target="deliveryMode === 'view' ? '_blank' : '_self'"
                           class="inline-flex items-center gap-1 px-3 py-1 text-xs rounded-md text-pink-700 bg-pink-50 hover:bg-pink-100">
                            <svg x-show="deliveryMode === 'download'" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                            XML
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg">
            <div class="space-y-6">
                <div class="p-4 bg-green-50 border-s-4 border-green-400 rounded-e-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20"></svg>
                        </div>
                        <div class="ms-3">
                            <h3 class="text-lg font-medium text-green-800"><?php esc_html_e('Product Synchronization', 'pulseem'); ?></h3>
                            <p class="text-sm text-green-700 mt-1">
                                <?php esc_html_e('View all synchronized products in the JSON file. Use the sync button to update existing products and add new ones from your store. Products already in the system will only be updated, not duplicated.', 'pulseem'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex gap-6 items-center">
                    <a
                        href="<?php echo esc_url(rest_url('pulseem/v1/get-products-json')); ?>"
                        target="_blank"
                        class="inline-flex items-center px-5 py-2.5 text-sm font-medium rounded-md text-pink-700 bg-pink-50 hover:bg-pink-100 transition-all duration-200 hover:shadow-md"
                        title="View current list of synchronized products"
                    >
                        <svg class="me-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                        </svg>
                        <?php esc_html_e('View Products List', 'pulseem'); ?>
                    </a>

                    <button
                        id="sync-products-btn"
                        type="button"
                        class="inline-flex items-center px-6 py-3 text-base font-medium rounded-md text-white bg-gradient-to-r from-pink-600 to-pink-500 hover:from-pink-700 hover:to-pink-600 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200 focus:ring-2 focus:ring-offset-2 focus:ring-pink-500 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:transform-none"
                        <?php if (!$pulseem_admin_model->getIsProductSync()) echo 'disabled'; ?>
                    >
                        <svg class="me-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <g class="animate-spin origin-center">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </g>
                        </svg>
                        <?php esc_html_e('Sync Store Products', 'pulseem'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
