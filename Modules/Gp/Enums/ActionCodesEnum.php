<?php

namespace Modules\Gp\Enums;

use App\Enum\Enum;

class ActionCodesEnum extends Enum
{
    const login = "lo000100";
    const logout = "lo000200";
    const changePassword = "lo000300";
    const resetPassword = "lo000400";
    const forgotPassword = "lo000500";
    const checkToken = "lo000600";
    const instUserProfile = "lo010100";
    const googleCheckAuth = "lo020100";
    const changeGoogleAuth = "lo020300";
    const createQrCode = "lo020200";
    const applogin = "oi000010";
    const applogout = "oi000020";
    const passpolicy = "oi000080";
    const appforgotPassword = "oi000060";
    const appresetPassword = "oi000050";
    const appchangePassword = "oi000040";
    const apppasstokenconfirm = "oi000070";
    const checkAppVersion = "oi000460";
    const sidebarcurcode = "gp013502";
    const getactuser = "gp020001";
    const loginactuser = "gp020202";
    const logoutactuser = "gp020402";
    const appRegister = 'oi000580';
    const appEmailConfirm = 'oi000590';
    const appSocialLogin = 'oi000600';
    const instUserToken = 'lo010101';
}
