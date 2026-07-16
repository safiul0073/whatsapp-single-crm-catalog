<?php
require 'components/session.php';
require 'components/helper.php';

checkIfAlreadyInstalled();

checkPreviousStepIsComplete('index');

$requiredExtensions = [
    'bcmath',
    'ctype',
    'curl',
    'dom',
    'exif',
    'fileinfo',
    'filter',
    'gd',
    'hash',
    'iconv',
    'intl',
    'json',
    'libxml',
    'mbstring',
    'mysqli',
    'openssl',
    'pdo',
    'pdo_mysql',
    'phar',
    'session',
    'simplexml',
    'sodium',
    'tokenizer',
    'xml',
    'xmlreader',
    'xmlwriter',
    'zip',
    'zlib',
];

$status = true;
$extensions = [];

foreach ($requiredExtensions as $extension) {
    $loadedStatus = extension_loaded($extension);

    if (! $loadedStatus) {
        $status = false;
    }

    $extensions[$extension] = [
        'status' => $loadedStatus,
        'name' => strtoupper($extension),
    ];
}

$currentPhpVersion = substr(phpversion(), 0, 3);
$requiredPhpVersion = '8.2';
$phpStatus = true;

if (! version_compare($currentPhpVersion, $requiredPhpVersion, '>=')) {
    $phpStatus = false;
    $status = false;
}

putSession('server-requirement', $status ? COMPLETE : INCOMPLETE);

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
                <div class="step-ratio">
                    <span>
                        <i class="fa-solid fa-circle"></i>
                    </span>
                </div>
                <span class="terms-text text-capitalize">
                    Server Requirement
                </span>
            </li>
            <li>
                <div class="step-ratio">
                    <span>

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
                Server Requirement
            </h5>
            <div class="terms-body-inner">
                <div class="server-header text-center mt-2">
                    <i class="fa-regular <?= $status ? 'fa-circle-check' : 'fa-circle-xmark text-danger' ?>""></i>
                        <h4>
                            Requirements Check
                        </h4>
                        <h5 class=" sub-title">
                        <?= $status ? '' : 'Fillup the required information and reload the page' ?>
                        </h5>
                </div>
                <div class="requarment-table">
                    <table class="table table-borderless">
                        <tbody>
                            <tr>
                                <td>Required PHP Version</td>
                                <td><?= $requiredPhpVersion ?> (Current: <?= $currentPhpVersion ?>)</td>
                                <td class="text-end"><button><i class="fa-regular <?= $phpStatus ? 'fa-circle-check' : 'fa-circle-xmark text-danger' ?>"></i></button></td>
                            </tr>
                            <?php foreach ($extensions as $extension => $data) { ?>
                                <tr>
                                    <td>Extension</td>
                                    <td><?= $data['name'] ?></td>
                                    <td class="text-end"><button><i class="fa-regular <?= $data['status'] ? 'fa-circle-check' : 'fa-circle-xmark text-danger' ?>"></i></button></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="cmn-border"></div>
                <div class="btn-area d-flex flex-wrap align-items-center gap-xxl-4 gap-3">
                    <a href="/installer" class="cmn-btn style2">
                        Go Back
                    </a>
                    <a href="/installer/folder-permissions.php" class="cmn-btn <?= $status ? '' : 'disabled' ?>">
                        Next Steps
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'components/footer.php'; ?>
