<?php

/***************************************************************************
 *
 *    Lock Content lugin (/inc/languages/espanol/lock.lang.php)
 *    Author: Neko
 *    Maintainer: © 2024 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    Allow users to hide content in their posts in exchange for replies or NewPoints currency.
 *
 ***************************************************************************
 ****************************************************************************
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****************************************************************************/

$l['lock'] = 'Lock Content';
$l['lock_desc'] = 'Lock Content es un complemento para ocultar contenido que se muestra cuando el usuario responde al hilo o paga puntos de NewPoints.';

$l['lock_nopermission_reply'] = 'Para ver el contenido oculto necesitas responder a este tema.';
$l['lock_nopermission_guest'] = "Para ver el contenido oculto necesitas <a href=\"{1}/member.php?action=register\">registrarte</a> o <a href=\"{1}/member.php?action=login\">iniciar sesión</a>.";
$l['lock_title'] = 'Contenido Oculto';
$l['lock_purchase'] = 'Paga {1} Puntos.';
$l['lock_purchase_yougot'] = ' Tu tienes {1} puntos.';
$l['lock_purchase_cost'] = '[{1} Puntos]';
$l['lock_purchase_confirm'] = 'Estas seguro de querer pagar {1} para ver el contenido oculto?';
$l['lock_purchase_desc'] = 'Para ver el contenido oculto necesitas pagar los puntos necesarios.';
$l['lock_purchase_error_no_funds'] = 'No tienes suficientes puntos para comprar este contenido.';

$l['lock_content_newpoints_page_logs_purchase'] = 'Lock Content Compra';
$l['lock_content_newpoints_page_logs_sell'] = 'Lock Content Venta';
$l['lock_content_newpoints_page_logs_post_link'] = '<a href="{1}/{2}">{3}</a>';

$l['lock_permission_maxcost'] = 'No tienes permiso para ocultar contenido por mas de {1} puntos.';