<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

?>
<section class="split">
    <div>
        <h2>Shop settings</h2>
        <div class="table-wrap compact">
            <table>
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Value</th>
                        <th>Ghi chú</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['settings'] as $setting): ?>
                        <tr>
                            <td><?= Formatter::h((string) $setting['setting_key']) ?></td>
                            <td><?= Formatter::h((string) $setting['setting_value']) ?></td>
                            <td><?= Formatter::h((string) ($setting['note'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div>
        <h2>Shipping zones</h2>
        <div class="table-wrap compact">
            <table>
                <thead>
                    <tr>
                        <th>Zone</th>
                        <th>Phí</th>
                        <th>Default</th>
                        <th>Active</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['shipping_zones'] as $zone): ?>
                        <tr>
                            <td><?= Formatter::h((string) $zone['zone_name']) ?></td>
                            <td><?= Formatter::moneyVnd($zone['fee_vnd']) ?></td>
                            <td><?= ((int) $zone['is_default'] === 1) ? 'Có' : '-' ?></td>
                            <td><?= ((int) $zone['is_active'] === 1) ? 'Có' : 'Không' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
