/**
 * Deterministic E2E users created by `database/seeders/E2eUsersSeeder.php`
 * via `php artisan e2e:bootstrap`. Keep these literals in sync with
 * `E2eUsersSeeder::USERS`.
 */
export type E2eUserRole = 'admin' | 'staff' | 'user';

export interface E2eUser {
    role: E2eUserRole;
    username: string;
    password: string;
    /** matches CLASS_* constant in include/functions_user.php */
    classId: number;
}

export const E2E_USERS: Record<E2eUserRole, E2eUser> = {
    admin: {
        role: 'admin',
        username: 'e2eadmin',
        password: 'E2eAdmin2026',
        classId: 16,
    },
    staff: {
        role: 'staff',
        username: 'e2estaff',
        password: 'E2eStaff2026',
        classId: 13,
    },
    user: {
        role: 'user',
        username: 'e2euser',
        password: 'E2eUser2026',
        classId: 1,
    },
};
