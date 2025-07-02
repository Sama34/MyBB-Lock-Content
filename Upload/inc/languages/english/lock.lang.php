<?php

/***************************************************************************
 *
 *    Lock Content plugin (/inc/languages/english/lock.lang.php)
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
$l['lock_desc'] = 'Allow users to hide content in their posts in exchange for replies or NewPoints currency.';

$l['lock_nopermission_reply'] = 'You must reply to this thread to view this content.';
$l['lock_nopermission_guest'] = "You must <a href=\"{1}/member.php?action=register\">register</a> or <a href=\"{1}/member.php?action=login\">login</a> to view this content.";
$l['lock_title'] = 'Hidden Content';
$l['lock_purchase'] = 'Pay {1} Points.';
$l['lock_purchase_yougot'] = ' You have {1} points.';
$l['lock_purchase_cost'] = '[{1} Points]';
$l['lock_purchase_confirm'] = 'Are you sure you want to pay {1} to view the content?';
$l['lock_purchase_desc'] = 'Please pay the required points to unlock the content.';
$l['lock_purchase_error_no_funds'] = 'You do not have enough points to purchase this content.';

$l['lock_content_newpoints_page_logs_purchase'] = 'Lock Content Purchase';
$l['lock_content_newpoints_page_logs_sell'] = 'Lock Content Sell';
$l['lock_content_newpoints_page_logs_post_link'] = '<a href="{1}/{2}">{3}</a>';

$l['lock_permission_maxcost'] = 'You are not allowed to charge more than {1} for your hidden content.';