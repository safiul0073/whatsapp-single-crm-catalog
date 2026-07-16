<?php
include 'components/session.php';
include 'components/helper.php';

checkIfAlreadyInstalled();

putSession('index', COMPLETE);

?>

<?php include 'components/header.php'; ?>

<section class="terms-section">
    <div class="container">
        <ul class="terms-header">
            <li>
                <div class="step-ratio">
                    <span>
                        <i class="fa-solid fa-circle"></i>
                    </span>
                </div>
                <span class="terms-text text-capitalize">
                    Terms Of Use
                </span>
            </li>
            <li>
                <div class="step-ratio">
                    <span>

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
                Terms Of Use
            </h5>
            <div class="terms-body-inner">
                <div class="content-box">
                    <h5>
                        General Terms and Conditions
                    </h5>
                    <p>
                        <p>Welcome to Quizix, an application that allows users to create quizzes and share them with others.</p>
                        <p>By purchasing the Regular License, you are granted an ongoing, non-exclusive, worldwide license to use the item under the terms described in this agreement.</p>
                        <p>These Terms and Conditions govern your use of our application only available at <?= appUrl() ?></p>
                    </p>
                </div>
                <div class="cmn-border"></div>
                <div class="content-box">
                    <h5>
                        You Can Do
                    </h5>
                    <ul class="you-dolist d-grid gap-3">
                        <li class="d-flex align-items-center gap-2">
                            <i class="fa-regular fa-circle-check"></i>
                            Use the Application on one (1) domain only.
                        </li>
                        <li class="d-flex align-items-center gap-2">
                            <i class="fa-regular fa-circle-check"></i>
                            Modify or edit the Application to suit your needs.
                        </li>
                        <li class="d-flex align-items-center gap-2">
                            <i class="fa-regular fa-circle-check"></i>
                            Translate the Application into any language of your choice.
                        </li>
                    </ul>
                </div>
                <div class="cmn-border"></div>
                <div class="content-box">
                    <h5>
                        You Cannot Do
                    </h5>
                    <ul class="you-dolist style2  d-grid gap-3">
                        <li class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-xmark"></i>
                            Reselling, distributing, or transferring the Application to any third party or individual by any means.
                        </li>
                        <li class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-xmark"></i>
                            Incorporating this product into other products that are sold on any market or affiliate websites.
                        </li>
                        <li class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-xmark"></i>
                            Using the Application on more than one domain.
                        </li>
                    </ul>
                </div>
                <div class="cmn-border"></div>
                <div class="content-box">
                    <h5>
                        Support
                    </h5>
                    <ul class="support-list style2  d-grid gap-3">
                        <li class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-circle-info"></i>
                            Support for this Application is provided as per the support policy of the marketplace.
                        </li>
                        <li class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-circle-info"></i>
                            Any issues arising from modifications or changes made by you are not covered under the support policy.
                        </li>
                    </ul>
                </div>
                <div class="cmn-border"></div>
                <div class="btn-area">
                    <a href="/installer/server-requirement.php" class="cmn-btn">
                        I Agree, Next Steps
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
include 'components/footer.php';
?>
