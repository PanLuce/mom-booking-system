<?php
/**
 * Template: User Detail Page
 */
$child_age = MomUserManager::get_instance()->calculate_child_age($user->child_birth_date);
?>

<div class="wrap">
    <h1><?php echo esc_html($user->name); ?></h1>

    <div class="user-detail-container">
        <!-- User Info Card -->
        <div class="user-info-card">
            <h2>Informace o uživateli</h2>

            <div class="user-details">
                <div class="detail-item">
                    <strong>Email:</strong> <?php echo esc_html($user->email); ?>
                </div>
                <div class="detail-item">
                    <strong>Telefon:</strong> <?php echo esc_html($user->phone ?: '-'); ?>
                </div>
                <div class="detail-item">
                    <strong>Jméno dítěte:</strong> <?php echo esc_html($user->child_name ?: '-'); ?>
                </div>
                <div class="detail-item">
                    <strong>Věk dítěte:</strong> <?php echo $child_age ?: '-'; ?>
                </div>
                <div class="detail-item">
                    <strong>Nouzový kontakt:</strong> <?php echo esc_html($user->emergency_contact ?: '-'); ?>
                </div>
                <div class="detail-item">
                    <strong>Registrován:</strong> <?php echo date('d.m.Y', strtotime($user->created_at)); ?>
                </div>
            </div>
        </div>

        <!-- Edit User Form -->
        <div class="edit-user-card">
            <h2>Upravit uživatele</h2>

            <form method="post" class="user-edit-form">
                <?php wp_nonce_field('mom_admin_action'); ?>
                <input type="hidden" name="mom_action" value="update_user">
                <input type="hidden" name="user_id" value="<?php echo $user->id; ?>">

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="user_name">Jméno a příjmení</label></th>
                        <td><input name="name" type="text" id="user_name" class="regular-text"
                                   value="<?php echo esc_attr($user->name); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="user_email">Email</label></th>
                        <td><input name="email" type="email" id="user_email" class="regular-text"
                                   value="<?php echo esc_attr($user->email); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="user_phone">Telefon</label></th>
                        <td><input name="phone" type="tel" id="user_phone" class="regular-text"
                                   value="<?php echo esc_attr($user->phone); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="child_name">Jméno dítěte</label></th>
                        <td><input name="child_name" type="text" id="child_name" class="regular-text"
                                   value="<?php echo esc_attr($user->child_name); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="child_birth_date">Datum narození dítěte</label></th>
                        <td><input name="child_birth_date" type="date" id="child_birth_date"
                                   value="<?php echo $user->child_birth_date; ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="emergency_contact">Nouzový kontakt</label></th>
                        <td><input name="emergency_contact" type="text" id="emergency_contact" class="regular-text"
                                   value="<?php echo esc_attr($user->emergency_contact); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="notes">Poznámky</label></th>
                        <td><textarea name="notes" id="notes" rows="3" class="large-text"><?php echo esc_textarea($user->notes); ?></textarea></td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="Uložit změny">
                    <a href="<?php echo admin_url('admin.php?page=mom-users'); ?>" class="button">Zpět na přehled</a>
                </p>
            </form>
        </div>

        <!-- User Bookings -->
        <div class="user-bookings-card">
            <h2>Rezervace uživatele</h2>

            <?php if (!empty($user_bookings)): ?>
                <div class="booking-stats">
                    <div class="stat-item">
                        <strong>Celkem rezervací:</strong> <?php echo $user_stats['total_bookings']; ?>
                    </div>
                    <div class="stat-item">
                        <strong>Potvrzené:</strong> <?php echo $user_stats['confirmed_bookings']; ?>
                    </div>
                    <div class="stat-item">
                        <strong>Budoucí:</strong> <?php echo $user_stats['future_bookings']; ?>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Kurz</th>
                            <th>Lekce</th>
                            <th>Datum</th>
                            <th>Status</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_bookings as $booking): ?>
                            <tr>
                                <td><?php echo esc_html($booking->course_title); ?></td>
                                <td>
                                    <strong><?php echo esc_html($booking->lesson_title); ?></strong>
                                    <br><small>Lekce <?php echo $booking->lesson_number; ?></small>
                                </td>
                                <td><?php echo date('d.m.Y H:i', strtotime($booking->date_time)); ?></td>
                                <td>
                                    <span class="status-<?php echo $booking->booking_status; ?>">
                                        <?php echo ucfirst($booking->booking_status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($booking->booking_status === 'confirmed' && strtotime($booking->date_time) > time()): ?>
                                        <button class="button button-small cancel-booking"
                                                data-booking-id="<?php echo $booking->id; ?>">
                                            Zrušit
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Uživatel nemá žádné rezervace.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.user-detail-container {
    display: grid;
    gap: 20px;
    margin-top: 20px;
}

.user-info-card,
.edit-user-card,
.user-bookings-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.user-details {
    display: grid;
    gap: 10px;
}

.detail-item {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.detail-item:last-child {
    border-bottom: none;
}

.booking-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
}

.stat-item {
    flex: 1;
}

.status-confirmed {
    color: #46b450;
    font-weight: bold;
}

.status-cancelled {
    color: #dc3545;
    font-weight: bold;
}
</style>
