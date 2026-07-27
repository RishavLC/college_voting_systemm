        </div><!-- /.content-wrapper -->
        <footer class="text-center py-3 text-muted small border-top" style="background:#fff;">
            &copy; <?= date('Y') ?> HDCVotes &mdash; Himalaya Darshan College. All Rights Reserved
        </footer>
    </div><!-- /.main-area -->
</div><!-- /.admin-shell -->

<!-- ========================================================================
     Popup Modal: Add Admin
     ======================================================================== -->
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header themed">
                <h5 class="modal-title"><i class="bi bi-person-fill-add me-1"></i> Add New Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addAdminForm">
                <div class="modal-body">
                    <div id="addAdminAlert"></div>
                    <div class="mb-3">
                        <label for="adminEmail">Email</label>
                        <input type="email" name="email" id="adminEmail" class="form-control" placeholder="admin@example.com" required>
                    </div>
                    <div class="mb-3">
                        <label for="adminPassword">Password</label>
                        <input type="password" name="password" id="adminPassword" class="form-control" placeholder="At least 6 characters" minlength="6" required>
                    </div>
                    <div class="mb-1">
                        <label for="adminConfirmPassword">Confirm Password</label>
                        <input type="password" name="confirm_password" id="adminConfirmPassword" class="form-control" placeholder="Re-enter password" minlength="6" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent" id="addAdminSubmitBtn"><i class="bi bi-check-lg me-1"></i>Add Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================
     Popup Modal: Import Students
     ======================================================================== -->
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header themed">
                <h5 class="modal-title"><i class="bi bi-file-earmark-arrow-up-fill me-1"></i> Import Students from CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="importForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div id="importAlert"></div>
                    <div class="mb-2">
                        <label for="csvFile">Select CSV file</label>
                        <input type="file" name="csv_file" id="csvFile" class="form-control" accept=".csv" required>
                        <div class="form-text">
                            Expected columns (in this order):
                            <code>student_id, student_name, student_batch, student_faculty, student_semester, student_phone, student_email</code>
                            <br>Maximum file size: 2MB.
                        </div>
                    </div>
                    <div id="importErrorList" class="d-none">
                        <div class="row g-2 mb-2 text-center">
                            <div class="col-4"><div class="stat-card justify-content-center py-2"><div><div class="stat-value" id="importProcessed">0</div><div class="stat-label">Processed</div></div></div></div>
                            <div class="col-4"><div class="stat-card justify-content-center py-2"><div><div class="stat-value text-success" id="importInserted">0</div><div class="stat-label">Inserted</div></div></div></div>
                            <div class="col-4"><div class="stat-card justify-content-center py-2"><div><div class="stat-value text-danger" id="importSkipped">0</div><div class="stat-label">Skipped</div></div></div></div>
                        </div>
                        <ul id="importErrorItems" style="max-height:180px; overflow-y:auto; font-size:0.85rem;"></ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent" id="importSubmitBtn"><i class="bi bi-upload me-1"></i>Upload & Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 991 && sidebar.classList.contains('open') &&
                !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    // ---- Add Admin popup ----
    const addAdminForm = document.getElementById('addAdminForm');
    const addAdminAlert = document.getElementById('addAdminAlert');
    const addAdminSubmitBtn = document.getElementById('addAdminSubmitBtn');
    if (addAdminForm) {
        addAdminForm.addEventListener('submit', function (e) {
            e.preventDefault();
            addAdminAlert.innerHTML = '';
            addAdminSubmitBtn.disabled = true;
            addAdminSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Adding...';

            fetch('add_admin.php', { method: 'POST', body: new FormData(addAdminForm) })
                .then(r => r.json())
                .then(data => {
                    addAdminAlert.innerHTML = '<div class="alert ' + (data.success ? 'alert-success' : 'alert-danger') + ' mb-3">' +
                        '<i class="bi ' + (data.success ? 'bi-check-circle' : 'bi-exclamation-circle') + ' me-1"></i>' + data.message + '</div>';
                    if (data.success) {
                        addAdminForm.reset();
                        setTimeout(() => {
                            const modalEl = document.getElementById('addAdminModal');
                            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                            addAdminAlert.innerHTML = '';
                        }, 1400);
                    }
                })
                .catch(() => {
                    addAdminAlert.innerHTML = '<div class="alert alert-danger mb-3"><i class="bi bi-exclamation-circle me-1"></i>Something went wrong. Please try again.</div>';
                })
                .finally(() => {
                    addAdminSubmitBtn.disabled = false;
                    addAdminSubmitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Add Admin';
                });
        });
    }

    // ---- Import Students popup ----
    const importForm = document.getElementById('importForm');
    const importAlert = document.getElementById('importAlert');
    const importSubmitBtn = document.getElementById('importSubmitBtn');
    const importErrorList = document.getElementById('importErrorList');
    if (importForm) {
        importForm.addEventListener('submit', function (e) {
            e.preventDefault();
            importAlert.innerHTML = '';
            importErrorList.classList.add('d-none');
            importSubmitBtn.disabled = true;
            importSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Importing...';

            const formData = new FormData(importForm);
            formData.append('ajax', '1');

            fetch('import_student.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        importAlert.innerHTML = '<div class="alert alert-success mb-3"><i class="bi bi-check-circle me-1"></i><strong>' + data.inserted + '</strong> student record(s) imported successfully from "' + data.file + '".</div>';
                        importForm.reset();
                        setTimeout(() => window.location.href = 'home.php?section=students', 1200);
                    } else if (data.status === 'duplicate') {
                        importAlert.innerHTML = '<div class="alert alert-warning mb-3"><i class="bi bi-exclamation-triangle me-1"></i>' + data.message + '</div>';
                    } else {
                        importAlert.innerHTML = '<div class="alert alert-danger mb-3"><i class="bi bi-x-circle me-1"></i>Import failed. See details below.</div>';
                        document.getElementById('importProcessed').textContent = data.processed ?? 0;
                        document.getElementById('importInserted').textContent = data.inserted ?? 0;
                        document.getElementById('importSkipped').textContent = data.skipped ?? 0;
                        const list = document.getElementById('importErrorItems');
                        list.innerHTML = '';
                        (data.errors || []).forEach(err => {
                            const li = document.createElement('li');
                            li.textContent = err;
                            list.appendChild(li);
                        });
                        importErrorList.classList.remove('d-none');
                    }
                })
                .catch(() => {
                    importAlert.innerHTML = '<div class="alert alert-danger mb-3"><i class="bi bi-exclamation-circle me-1"></i>Something went wrong. Please try again.</div>';
                })
                .finally(() => {
                    importSubmitBtn.disabled = false;
                    importSubmitBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Upload & Import';
                });
        });
    }
</script>
</body>
</html>
