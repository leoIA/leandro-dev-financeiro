<?php
/**
 * @file    SobreController.php
 * @package App\Controllers
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Controller da página "Sobre / Contato".
 *
 * Rota:
 *   GET /sobre → index() — exibe informações do sistema, módulos,
 *                contato do desenvolvedor e licença.
 *
 * Acesso: qualquer usuário autenticado (sem restrição de perfil).
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;

class SobreController
{
    public function index(): void
    {
        Auth::require();
        Response::view('sobre/index', [
            'pageTitle' => 'Sobre / Contato',
        ]);
    }
}
