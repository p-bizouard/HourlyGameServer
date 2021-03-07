/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import "admin-lte/dist/css/adminlte.css";
import "admin-lte/dist/js/adminlte.js";
import "@fortawesome/fontawesome-free/js/all.js";
import * as $ from "jquery";
import "bootstrap";
import moment from "moment";

// start the Stimulus application
import "./bootstrap";

// specific
import "./styles/app.css";

$("#modal-delete-serverUser").on("show.bs.modal", function (e) {
    const userNickname = $(e.relatedTarget).data("user");
    const serverUserId = $(e.relatedTarget).data("user-server-id");

    const $p = $(this).find(".modal-body p");
    $p.text($p.text().replace("__USER__", userNickname));

    $(this).find("#remove_server_user_serverUser").val(serverUserId);
});

$("[data-moment-fromnow]").each(function () {
    $(this).text(moment($(this).data("moment-fromnow")).fromNow());
});

$(".card-actions a").on("click", function (e) {
    $(".card-actions a").addClass("disabled");
    $(this).find("svg").remove();
    $(this).html('<i class="fas fa-cog fa-spin"></i>' + $(this).text());

    const currentDate = new Date();

    $.get($(this).attr("href"));

    const getLogs = function () {
        $.get(
            SERVER_LOGS_URL + "?date=" + currentDate.toISOString(),
            function (results) {
                $("#server-logs").html("");
                results.forEach(function (result) {
                    let $li = $("<li/>").text(
                        result.created +
                            " - " +
                            result.type +
                            " - " +
                            result.message
                    );

                    if (result.type === "success") {
                        $li.addClass("text-success");
                    } else if (result.type === "error") {
                        $li.addClass("text-danger");
                    }

                    $("#server-logs").append($li);
                });
                setTimeout(getLogs, 5000);
            }
        );
    };
    getLogs();

    e.preventDefault();
});
