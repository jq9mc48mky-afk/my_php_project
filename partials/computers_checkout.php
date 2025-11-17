<?php
/**
 * Partial View: Computer Check-out Form
 *
 * This file is included by 'computers.php' (case 'checkout')
 * It renders the form to check out an asset to a specific user.
 *
 * @global array $computer The computer data being checked out.
 * @global array $users List of all users for the dropdown.
 * @global string $csp_nonce The Content Security Policy nonce.
 */
?>
<h1 class="mb-4">Check Out Asset</h1>
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm rounded-3">
                    <div class="card-body">
                        <form method="POST" action="index.php?page=computers">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="computer_id" value="<?php echo htmlspecialchars($computer['id']); ?>">
                            <div class="mb-3">
                                <label class="form-label">Asset Tag</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($computer['asset_tag']); ?>" readonly disabled>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Model</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($computer['model']); ?>" readonly disabled>
                            </div>
                            <div class="mb-3">
                                <label for="assigned_to_user_id" class="form-label">Assign To User <span class="text-danger">*</span></label>
                                <select class="form-select" id="assigned_to_user_id" name="assigned_to_user_id" required>
                                    <option value="" selected disabled>Select a user</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['full_name']); ?> (User #<?php echo htmlspecialchars($user['username']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <hr class="my-4">
                            <div class="d-flex justify-content-end">
                                <a href="index.php?page=computers" class="btn btn-secondary me-2">Cancel</a>
                                <button type="submit" name="check_out" class="btn btn-success">
                                    <i class="bi bi-box-arrow-up-right"></i> Complete Check-out
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="alert alert-info">
                    <h5><i class="bi bi-info-circle-fill"></i> What This Does:</h5>
                    <p>Checking out this asset will:</p>
                    <ul>
                        <li>Set its status to "Assigned".</li>
                        <li>Assign it to the selected user.</li>
                        <li>Create a "Checked Out" entry in the asset's history log.</li>
                    </ul>
                </div>
            </div>
        </div>
        <script nonce="<?php echo htmlspecialchars($csp_nonce ?? ''); ?>">
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.initTomSelect === 'function') {
                window.initTomSelect('#assigned_to_user_id');
            }
        });
        </script>