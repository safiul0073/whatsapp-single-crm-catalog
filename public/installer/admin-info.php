<?php
require 'components/session.php';
require 'components/helper.php';

checkIfAlreadyInstalled();

checkPreviousStepIsComplete('folder-permissions');

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
                <div class="step-ratio active">
                    <span>
                        <i class="fa-solid fa-check"></i>
                    </span>
                </div>
                <span class="terms-text text-capitalize">
                    Folder Permission
                </span>
            </li>
            <li>
                <div class="step-ratio">
                    <span>
                        <i class="fa-solid fa-circle"></i>
                    </span>
                </div>
                <span class="terms-text text-capitalize">
                Installations and configuration
                </span>
            </li>
        </ul>
        <div class="terms-body">
            <h5 class="title">
                Database & Admin Information
            </h5>
            <div class="terms-body-inner">
                <form action="post.php" class="database-form d-grid gap-8" method="POST">
                    <?php if ($adminInfoError = getSession('admin-info-error')) { ?>
                        <li class="alert alert-danger" role="alert">
                            <?= $adminInfoError ?>
                        </li>
                    <?php unsetSession('admin-info-error');
                    } ?>
                    <div class="application-form-box row">
                        <div class="col-md-12">
                        <h5>
                            Enter Your Purchase Code
                        </h5>
                        <div class="form-grp">
                            <label for="event1">Purchase Code</label>
                            <input type="text" name="purchase_code" id="event1" placeholder="Enter Purchase Code" class="<?= hasError('purchase_code') ? 'error' : '' ?>" value="<?= old('purchase_code') ?>">
                        </div>
                        <?php if ($message = error('purchase_code')) { ?>
                            <span class="error-message"><?= $message ?></span>
                        <?php } ?>
                        </div>
                    </div>
                    <div class="application-form-box row">
                        <div class="col-md-6">
                        <h5>
                            Application URL (Remove slash at the end "/")
                        </h5>
                        <div class="form-grp">
                            <label for="event1">App URL</label>
                            <input type="text" name="app_url" id="event1" placeholder="{{ __('Enter Title') }}" class="<?= hasError('app_url') ? 'error' : '' ?>" value="<?= (old('app_url') ?? rtrim(appUrl('app_url'), '/')) ?>">
                        </div>
                        <?php if ($message = error('app_url')) { ?>
                            <span class="error-message"><?= $message ?></span>
                        <?php } ?>
                        </div>

                        <div class="col-md-6">
                        <h5>
                            Application Name
                        </h5>
                        <div class="form-grp">
                            <label for="event1">Application Name</label>
                            <input type="text" name="app_name" id="event1" placeholder="Enter Application Name" class="<?= hasError('app_name') ? 'error' : '' ?>" value="<?= (old('app_name') ?? 'Quizix') ?>">
                        </div>
                        <?php if ($message = error('app_name')) { ?>
                            <span class="error-message"><?= $message ?></span>
                        <?php } ?>
                        </div>
                    </div>
                    <div class="application-form-box">
                        <h5>
                            Database Information
                        </h5>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="form-grp">
                                    <label for="event2">Database Name</label>
                                    <input type="text" name="db_name" id="event2" placeholder="Enter Database Name" class="<?= hasError('db_name') ? 'error' : '' ?>" value="<?= old('db_name') ?>">
                                </div>
                                <?php if ($message = error('db_name')) { ?>
                                    <span class="error-message"><?= $message ?></span>
                                <?php } ?>
                            </div>
                            <div class="col-md-6">
                                <div class="form-grp">
                                    <label for="event3">Database User</label>
                                    <input type="text" name="db_user" id="event3" placeholder="Enter Database User" class="<?= hasError('db_user') ? 'error' : '' ?>" value="<?= old('db_user') ?>">
                                </div>
                                <?php if ($message = error('db_user')) { ?>
                                    <span class="error-message"><?= $message ?></span>
                                <?php } ?>
                            </div>
                            <div class="col-md-6">
                                <div class="form-grp">
                                    <label for="event5">Database Password</label>
                                    <input type="text" name="db_pass" id="event5" placeholder="Enter Database Password" class="<?= hasError('db_pass') ? 'error' : '' ?>" value="<?= old('db_pass') ?>">
                                </div>
                                <?php if ($message = error('db_pass')) { ?>
                                    <span class="error-message"><?= $message ?></span>
                                <?php } ?>
                            </div>
                            <div class="col-md-6">
                                <div class="form-grp">
                                    <label for="event6">Database Host</label>
                                    <input type="text" name="db_host" id="event6" placeholder="Enter Database Host" class="<?= hasError('db_host') ? 'error' : '' ?>" value="<?= old('db_host') ?>">
                                </div>
                                <?php if ($message = error('db_host')) { ?>
                                    <span class="error-message"><?= $message ?></span>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="application-form-box">
                        <h5>
                            Admin Login Information
                        </h5>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="form-grp">
                                    <label for="event7">First Name</label>
                                    <input type="text" name="first_name" id="event7" placeholder="Enter First Name" class="<?= hasError('first_name') ? 'error' : '' ?>" value="<?= old('first_name') ?>">
                                </div>
                                <?php if ($message = error('first_name')) { ?>
                                    <span class="error-message"><?= $message ?></span>
                                <?php } ?>
                            </div>
                            <div class="col-md-6">
                                <div class="form-grp">
                                    <label for="event7">Last Name</label>
                                    <input type="text" name="last_name" id="event7" placeholder="Enter Last Name" class="<?= hasError('last_name') ? 'error' : '' ?>" value="<?= old('last_name') ?>">
                                </div>
                                <?php if ($message = error('last_name')) { ?>
                                    <span class="error-message"><?= $message ?></span>
                                <?php } ?>
                            </div>
                            <div class="col-md-6">
                                <div class="form-grp">
                                    <label for="event8">Email Address</label>
                                    <input type="email" name="email" id="event8" placeholder="Enter Email" class="<?= hasError('email') ? 'error' : '' ?>" value="<?= old('email') ?>">
                                </div>
                                <?php if ($message = error('email')) { ?>
                                    <span class="error-message"><?= $message ?></span>
                                <?php } ?>
                            </div>

                            <div class="col-md-6">
                                <div class="form-grp">
                                    <label for="event7">Password</label>
                                    <input type="text" name="password" id="event7" placeholder="Enter Password" class="<?= hasError('password') ? 'error' : '' ?>" value="<?= old('password') ?>">
                                </div>
                                <?php if ($message = error('password')) { ?>
                                    <span class="error-message"><?= $message ?></span>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="cmn-border"></div>
                    <div class="btn-area d-flex flex-wrap align-items-center gap-xxl-4 gap-3">
                        <a href="/installer/folder-permissions.php" class="cmn-btn style2">
                            Go Back
                        </a>
                        <button type="submit" class="cmn-btn btn btn-primary">
                            Install
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<?php include 'components/footer.php'; ?>
