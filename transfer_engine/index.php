<?php
/**
 * Root Entry Point Redirector
 * 
 * Redirects to the proper public/index.php entry point
 * This file is optional but provides convenience for direct access
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 */

// Redirect to public directory
header('Location: public/index.php');
exit;
