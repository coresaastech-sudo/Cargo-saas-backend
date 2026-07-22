<?php

namespace Modules\Gp\Enums;

use App\Enum\Enum;

class NotAuthActionCodesEnum extends Enum
{
    const login = ActionCodesEnum::login;
    const forgotPassword = ActionCodesEnum::forgotPassword;
    const resetPassword = ActionCodesEnum::resetPassword;
    const logout = ActionCodesEnum::logout;
    const changePassword = ActionCodesEnum::changePassword;
    const checkToken = ActionCodesEnum::checkToken;
    const instUserProfile = ActionCodesEnum::instUserProfile;
    const instUserToken = ActionCodesEnum::instUserToken;
    const googleCheckAuth = ActionCodesEnum::googleCheckAuth;
    const createQrCode = ActionCodesEnum::createQrCode;
    const changeGoogleAuth = ActionCodesEnum::changeGoogleAuth;
    const checkStatusSupervisor = "tr010450";
    const listOfReport = "re010011";

    const applogin = ActionCodesEnum::applogin;
    const appforgotPassword = ActionCodesEnum::appforgotPassword;
    const appresetPassword = ActionCodesEnum::appresetPassword;
    const apppasstokenconfirm = ActionCodesEnum::apppasstokenconfirm;
    const checkAppVersion = ActionCodesEnum::checkAppVersion;
    const passPolicy = ActionCodesEnum::passpolicy;
    const sidebarcurcode = ActionCodesEnum::sidebarcurcode;
    const getactuser = ActionCodesEnum::getactuser;
    const loginactuser = ActionCodesEnum::loginactuser;
    const logoutactuser = ActionCodesEnum::logoutactuser;
    const appchangePassword = ActionCodesEnum::appchangePassword;
    const appRegister = ActionCodesEnum::appRegister;
    const appEmailConfirm = ActionCodesEnum::appEmailConfirm;
    const appSocialLogin = ActionCodesEnum::appSocialLogin;
}
