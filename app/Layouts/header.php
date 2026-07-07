<?php declare(strict_types=1);
/**
 * @file    header.php
 * @package App\Layouts
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Cabeçalho HTML: abre <html>, <head> com CSS CDNs, <body>,
 *          wrapper flex e container principal. Sidebar e topbar são
 *          incluídos separadamente por Response::view().
 *
 * Variáveis esperadas:
 *  - $pageTitle (string) — título da página (default "Dashboard").
 */
use App\Core\Csrf;

$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — Leandro DEV Financeiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="public/css/app.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="wrapper d-flex">
