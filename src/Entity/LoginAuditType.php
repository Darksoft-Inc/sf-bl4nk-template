<?php

namespace App\Entity;

/**
 * How the security system authenticated the user:
 * "login" is a form login, "resume" is the restore of a persistent session
 * from a remember-me cookie.
 */
enum LoginAuditType: string
{
    case Login = 'login';
    case Resume = 'resume';
}
