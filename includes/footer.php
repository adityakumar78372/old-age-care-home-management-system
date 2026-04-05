            </div> <!-- End Main Content Container -->
        </div> <!-- End #content -->
    </div> <!-- End .wrapper -->

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery (Needed for DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js" defer></script>

    <!-- DataTables JS & Export Buttons (deferred — non-blocking) -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" defer></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js" defer></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js" defer></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js" defer></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js" defer></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js" defer></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>

    <script>
    // Initialize DataTables after all deferred scripts are ready
    window.addEventListener('load', function() {
        if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') return;
        if (!$('.filterable-table').length) return;

        $.fn.dataTable.ext.errMode = 'none';

        var dt = $('.filterable-table').DataTable({
            dom: "<'row mb-3'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            buttons: [
                { extend: 'excelHtml5', className: 'btn btn-success btn-sm me-2', text: '<i class="fas fa-file-excel"></i> Excel' },
                { extend: 'pdfHtml5',   className: 'btn btn-danger btn-sm me-2',  text: '<i class="fas fa-file-pdf"></i> PDF' },
                { extend: 'print',      className: 'btn btn-info text-white btn-sm', text: '<i class="fas fa-print"></i> Print' }
            ],
            paging:      true,
            ordering:    true,
            info:        true,
            responsive:  true,
            deferRender: true,
            language:    { search: "", searchPlaceholder: "Search records..." }
        });

        // Hide legacy search input if DataTables search is active
        $('#tableSearch').hide();

        // Fix: sync external status filter with DataTables column search
        // (used by payments page — avoids display:none conflict)
        var statusFilter = document.getElementById('statusFilter');
        if (statusFilter && dt) {
            statusFilter.addEventListener('change', function() {
                var val = this.value === 'all' ? '' : this.value;
                // Use DataTables search on column 3 (Status column in payments table)
                dt.column(3).search(val, false, false).draw();
            });
        }
    });
    </script>

    <script>
    // Global toast notification using SweetAlert2
    function showToast(message, type) {
        type = type || 'success';
        const iconMap = { danger: 'error', error: 'error', warning: 'warning', info: 'info', success: 'success', primary: 'info' };
        const iconType = iconMap[type] || 'info';
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: iconType,
            title: message,
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            background: isDark ? '#1e293b' : '#ffffff',
            color:      isDark ? '#f8fafc' : '#1e293b'
        });
    }

    <?php
    // Display flash messages — using unified get_flash_message() only
    // (removed legacy $_SESSION['msg'] system to prevent double-toast)
    if (function_exists('get_flash_message')) {
        $flash = get_flash_message();
        if ($flash) {
            $fmsg  = addslashes(htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'));
            $ftype = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
            echo "showToast('{$fmsg}', '{$ftype}');";
        }
    }
    ?>
    </script>

    <script>
    // Ripple effect for custom buttons
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-primary-custom, .btn-success, .btn-danger');
        if (btn) {
            const rect   = btn.getBoundingClientRect();
            const ripple = document.createElement('span');
            ripple.style.left = (e.clientX - rect.left) + 'px';
            ripple.style.top  = (e.clientY - rect.top)  + 'px';
            ripple.classList.add('ripple');
            btn.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        }
    });
    </script>

</body>
</html>
<?php
// Flush output buffer
if (ob_get_level()) {
    ob_end_flush();
}
?>
