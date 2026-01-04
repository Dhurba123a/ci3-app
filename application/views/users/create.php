<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Create User</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">

            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Create User</h5>
                </div>

                <div class="card-body">

                    <div id="alertBox" class="alert d-none" role="alert"></div>

                    <form id="createUserForm" enctype="multipart/form-data" novalidate>
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                            <div class="invalid-feedback">Name is required.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Age</label>
                            <input type="number" name="age" class="form-control" min="1" required>
                            <div class="invalid-feedback">Age must be a positive number.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="1">Active</option>
                                <option value="0">Suspended</option>
                            </select>
                        </div>

                        <hr class="my-4">

                        <div class="mb-3">
                            <label class="form-label">Upload Image</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                            <div class="form-text">JPG/PNG/GIF/WEBP up to 5MB.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Upload PDF</label>
                            <input type="file" name="pdf" class="form-control" accept="application/pdf">
                            <div class="form-text">PDF up to 10MB.</div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="<?php echo site_url('users'); ?>" class="btn btn-outline-secondary">
                                ← Back
                            </a>

                            <button id="submitBtn" type="submit" class="btn btn-primary">
                                Create User
                            </button>
                        </div>
                    </form>

                    <div id="uploadResult" class="mt-4 d-none">
                        <h6 class="mb-2">Saved Files</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Image
                                <span id="imagePath" class="text-muted small"></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                PDF
                                <span id="pdfPath" class="text-muted small"></span>
                            </li>
                        </ul>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<!-- Bootstrap JS (optional) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    async function uploadToR2(file) {
        if (!file) return null;

        // Use proxy upload to avoid CORS issues
        const formData = new FormData();
        formData.append('file', file);

        try {
            const res = await fetch("<?php echo site_url('upload/proxy_upload'); ?>", {
                method: "POST",
                body: formData
            });

            if (!res.ok) {
                const errorData = await res.json().catch(() => ({ error: 'Unknown error' }));
                throw new Error(errorData.error || 'Upload failed');
            }

            const data = await res.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Upload failed');
            }

            return {
                key: data.object_key,
                url: data.public_url
            };
        } catch (err) {
            console.error('Upload error:', err);
            throw err;
        }
    }

$(function () {
    const $form = $('#createUserForm');
    const $btn  = $('#submitBtn');
    const $alert = $('#alertBox');
    const $result = $('#uploadResult');
    const $imagePath = $('#imagePath');
    const $pdfPath = $('#pdfPath');

    function showAlert(type, msg) {
        $alert.removeClass('d-none alert-success alert-danger alert-warning')
              .addClass('alert-' + type)
              .text(msg);
    }

    function resetAlert() {
        $alert.addClass('d-none').text('');
    }

    $form.on('submit', async function (e) {
    e.preventDefault();
    resetAlert();

    if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        showAlert('warning', 'Please fix the highlighted fields.');
        return;
    }

    $btn.prop('disabled', true).text('Uploading...');

    try {
        const imageFile = $form.find('input[name="image"]')[0].files[0];
        const pdfFile   = $form.find('input[name="pdf"]')[0].files[0];

        // 1. Upload files to R2
        const imageUpload = await uploadToR2(imageFile);
        const pdfUpload   = await uploadToR2(pdfFile);

        // 2. Build payload (NO FILES)
        const payload = {
            name:  $form.find('input[name="name"]').val(),
            age:   $form.find('input[name="age"]').val(),
            status:$form.find('select[name="status"]').val(),
            image_path: imageUpload ? imageUpload.key : null,
            pdf_path:   pdfUpload ? pdfUpload.key : null
        };

        // 3. Submit metadata to backend
        const res = await $.ajax({
            url: "<?php echo site_url('users/create'); ?>",
            type: "POST",
            contentType: "application/json",
            dataType: "json",
            data: JSON.stringify(payload)
        });

        if (res && res.success) {
            showAlert('success', res.message || 'Created successfully.');

            $result.removeClass('d-none');
            $imagePath.text(imageUpload ? imageUpload.url : '—');
            $pdfPath.text(pdfUpload ? pdfUpload.url : '—');

            $form[0].reset();
            $form.removeClass('was-validated');
        } else {
            showAlert('danger', res.message || 'Something went wrong.');
        }

    } catch (err) {
        console.error(err);
        showAlert('danger', err.message || 'Upload failed.');
        } finally {
            $btn.prop('disabled', false).text('Create User');
        }
    });

}); 
</script>

</body>
</html>

