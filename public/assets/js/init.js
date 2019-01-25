/**
 * @file init.js
 * Initialization of frontend of the application goes here
 *
 * @author Pavel Pavlov <Pavel.Pavlov@alera.ru>
 * @date 12/31/13
 * @time 3:44 AM
 * @license LICENSE.md
 *
 * @package PHPCI
 */

$(function () {
    $('#latest-builds').on('latest-builds:reload', bindAppDeleteEvents);
    $('#latest-builds').trigger('latest-builds:reload');
    $('.treeview').not('.treeview_inited').addClass('treeview_inited').on("click", function(){
        $(this).toggle('active');
    })
});

function bindAppDeleteEvents () {
    $('.phpci-app-delete-build').on('click', function (e) {
        e.preventDefault();

        confirmDelete(e.target.href, 'Build').onClose = function () {
            window.location.reload();
        };

        return false;
    });

    $('.phpci-app-delete-user').on('click', function (e) {
        e.preventDefault();

        confirmDelete(e.target.href, 'User', true);

        return false;
    });
}
