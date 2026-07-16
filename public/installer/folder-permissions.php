<?php
require 'components/session.php';
require 'components/helper.php';

checkIfAlreadyInstalled();

checkPreviousStepIsComplete('server-requirement');

$requiredPerm = '0755';
$dirs = ['../../bootstrap/cache/', '../../storage/', '../../storage/app/', '../../storage/framework/', '../../storage/logs/', '../../resources/', '../../database/'];
$permissions = [];

$status = true;

foreach ($dirs as $dir) {
    $perm = substr(sprintf('%o', fileperms($dir)), -4);

    if ($perm < $requiredPerm) {
        $status = false;
    }

    $permissions[$dir] = [
        'status' => $perm >= $requiredPerm,
        'name' => str_replace('../../', '', $dir),
        'perm' => $perm,
    ];
}

putSession('folder-permissions', $status ? COMPLETE : INCOMPLETE);

?>

<?php include 'components/header.php'; ?>
<section class="terms-section">
    <div class="container">
        <ul class="terms-header">
            <li>
                <div class="step-ratio active">
                    <span>
                        <i class="fa-solid fa-check"></i>
                    </span>
                </div>
                <span class="terms-text text-capitalize">
                    Terms Of Use
                </span>
            </li>
            <li>
                <div class="step-ratio active">
                    <span>
                        <i class="fa-solid fa-check"></i>
                    </span>
                </div>
                <span class="terms-text text-capitalize">
                    Server Requirement
                </span>
            </li>
            <li>
                <div class="step-ratio">
                    <span>
                        <i class="fa-solid fa-circle"></i>
                    </span>
                </div>
                <span class="terms-text text-capitalize">
                    Folder Permission
                </span>
            </li>
            <li>
                <div class="step-ratio">
                    <span>

                    </span>
                </div>
                <span class="terms-text text-capitalize">
                Installations and configuration
                </span>
            </li>
        </ul>
        <div class="terms-body">
            <h5 class="title">
                Folder Permission
            </h5>
            <div class="terms-body-inner">
                <div class="server-header text-center mt-2">
                    <i class="fa-regular <?= $status ? 'fa-circle-check' : 'fa-circle-xmark text-danger' ?>"></i>
                    <h4>
                        Folder Permission Check
                    </h4>
                </div>
                <div class="requarment-table">
                    <table class="table table-borderless">
                        <thead class="thead-dark">
                            <tr class="text-white">
                                <th scope="col">Folder</th>
                                <th scope="col">Permission</th>
                                <th scope="col" class="text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($permissions as $key => $value) { ?>
                                <tr>
                                    <td><?= $value['name'] ?></td>
                                    <td><?= $requiredPerm ?> (Current: <?= $value['perm'] ?>)</td>
                                    <td class="text-end"><button><i class="fa-regular <?= $value['status'] ? 'fa-circle-check' : 'fa-circle-xmark text-danger' ?>"></i></button></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="cmn-border"></div>
                <div class="btn-area d-flex flex-wrap align-items-center gap-xxl-4 gap-3">
                    <a href="/installer/server-requirement.php" class="cmn-btn style2">
                        Go Back
                    </a>
                    <a href="/installer/admin-info.php" class="cmn-btn <?= $status ? '' : 'disabled' ?>">
                        Next Steps
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'components/footer.php'; ?>
