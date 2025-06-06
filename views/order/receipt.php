<?php
use yii\helpers\Html;

$orderStatus = $order->orderStatus->name ?? 'Неизвестно';

$orderPrice = $order->order_price ?? 0;
$orderCashback = $order->order_cashback ?? 0;
?>

<h1>Чек заказа №<?= Html::encode($order->id) ?></h1>

<style>
    table.order-table {
        border-collapse: collapse;
        width: 100%;
        max-width: 600px;
    }

    table.order-table th,
    table.order-table td {
        border: 1px solid #ddd;
        padding: 8px;
    }

    table.order-table th {
        background-color: #f2f2f2;
        text-align: left;
        width: 40%;
    }

    /* Блок итогов */
    .totals-block {
        max-width: 600px;
        margin-top: 20px;
        text-align: right;
        font-size: 1.1em;
    }

    .totals-block div {
        margin: 5px 0;
    }

    /* Адаптивность */
    @media (max-width: 600px) {

        table.order-table,
        table.order-table thead,
        table.order-table tbody,
        table.order-table th,
        table.order-table td,
        table.order-table tr {
            display: block;
            width: 100%;
        }

        table.order-table tr {
            margin-bottom: 15px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }

        table.order-table th {
            display: none;
        }

        table.order-table td {
            border: none;
            position: relative;
            padding-left: 50%;
            text-align: left;
        }

        table.order-table td::before {
            content: attr(data-label);
            position: absolute;
            left: 10px;
            top: 8px;
            font-weight: bold;
            white-space: nowrap;
        }

        .totals-block {
            text-align: left;
            font-size: 1em;
        }
    }
</style>

<table class="order-table">
    <tbody>
        <tr>
            <th>Название товара</th>
            <td data-label="Название товара"><?= Html::encode($order->order_name) ?></td>
        </tr>
        <tr>
            <th>Дата оформления</th>
            <td data-label="Дата оформления"><?= date('d.m.Y H:i', strtotime($order->order_date)) ?></td>
        </tr>
        <tr>
            <th>Метод оплаты</th>
            <td data-label="Метод оплаты"><?= $order->payment_method ? 'Карта' : 'Кэшбек' ?></td>
        </tr>
        <?php if ($order->payment_method && $order->card_number): ?>
            <tr>
                <th>Карта</th>
                <td data-label="Карта"><?= Html::encode($order->card_number) ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <th>Статус</th>
            <td data-label="Статус"><?= Html::encode($orderStatus) ?></td>
        </tr>
    </tbody>
</table>

<div class="totals-block">
    <div>Стоимость: <?= Html::encode($orderPrice) ?> руб.</div>
    <div>Полученный кэшбек: <?= Html::encode($orderCashback ?: '—') ?> баллов</div>
</div>