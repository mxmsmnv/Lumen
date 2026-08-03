(function(){
    if(window.lumenCopyText) return;

    window.lumenUpdateBulkActions = function() {
        var checked = document.querySelectorAll(".LumenCheckbox:checked").length;
        var total = document.querySelectorAll(".LumenCheckbox").length;
        var btn = document.getElementById("LumenBulkDelete");
        var all = document.getElementById("LumenSelectAll");
        var count = document.getElementById("LumenBulkCount");

        if(btn) btn.disabled = checked < 1;
        if(all) {
            all.checked = total > 0 && checked === total;
            all.indeterminate = checked > 0 && checked < total;
        }
        if(count) {
            count.textContent = checked > 0
                ? checked + " " + (count.getAttribute("data-selected") || "selected")
                : (count.getAttribute("data-empty") || "No videos selected");
        }
    };

    window.lumenCopyText = function(btn) {
        var el = btn;
        if(el.getAttribute("aria-disabled") === "true") return false;

        var code = el.getAttribute("data-code") || "";
        var copied = el.getAttribute("data-copy-copied") || el.textContent;
        var original = el.getAttribute("data-copy-label") || el.textContent;
        var originalHtml = el.innerHTML;

        var finish = function(ok) {
            if(!ok) return;
            el.setAttribute("aria-disabled", "true");
            el.textContent = copied;
            setTimeout(function() {
                el.innerHTML = originalHtml || original;
                el.removeAttribute("aria-disabled");
            }, 1500);
        };

        if(window.navigator && window.navigator.clipboard && window.navigator.clipboard.writeText) {
            try {
                window.navigator.clipboard.writeText(code).then(function() {
                    finish(true);
                }, function() {
                    finish(false);
                });
                return false;
            } catch(e) {}
        }

        if(window.document && window.document.createElement && window.document.body) {
            var ta = window.document.createElement("textarea");
            ta.style.position = "fixed";
            ta.style.opacity = "0";
            ta.value = code;
            window.document.body.appendChild(ta);
            ta.select();
            var copiedOk = false;
            if(window.document.execCommand) copiedOk = window.document.execCommand("copy");
            window.document.body.removeChild(ta);
            finish(copiedOk);
        }

        return false;
    };

    window.lumenConfirm = function(btn) {
        if(btn && btn.disabled) return false;
        var message = btn.getAttribute("data-confirm") || "";
        return confirm(message);
    };

    var syncAdminNav = function() {
        var hash = window.location.hash || "#overview";
        document.querySelectorAll(".lumen-admin-nav li").forEach(function(item) {
            var link = item.querySelector("a");
            item.classList.toggle("uk-active", !!link && link.getAttribute("href") === hash);
        });
    };

    window.addEventListener("hashchange", syncAdminNav);
    if(document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", syncAdminNav);
    } else {
        syncAdminNav();
    }
})();
