<?php
/**
 * Template: templates/admin/lesson-detail.php
 * Detailed lesson management page
 */

$lesson = $lesson_data['lesson'];
$participants = $lesson_data['participants'];
$available_spots = $lesson_data['available_spots'];
$days = ['', 'Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'];
?>

<div class="wrap">
    <h1><?php echo esc_html($lesson->title); ?></h1>

    <div class="lesson-detail-container">
        <!-- Lesson Information Card -->
        <div class="lesson-info-card">
            <h2>Informace o lekci</h2>

            <div class="lesson-schedule">
                <div class="schedule-item">
                    <strong>Datum:</strong>
                    <?php echo date('d.m.Y', strtotime($lesson->date_time)); ?>
                    (<?php echo $days[date('N', strtotime($lesson->date_time))]; ?>)
                </div>

                <div class="schedule-item">
                    <strong>Čas:</strong>
                    <?php echo $lesson->start_time; ?> - <?php echo $lesson->end_time; ?>
                    <span class="duration">(<?php echo $lesson->lesson_duration; ?> min)</span>
                </div>

                <div class="schedule-item">
                    <strong>Kurz:</strong>
                    <a href="<?php echo admin_url('admin.php?page=mom-course-new&edit=' . $lesson->course_id); ?>">
                        <?php echo esc_html($lesson->course_title); ?>
                    </a>
                </div>

                <div class="schedule-item">
                    <strong>Kapacita:</strong>
                    <span class="capacity-info">
                        <?php echo count($participants); ?>/<?php echo $lesson->max_capacity; ?>
                        <?php if ($available_spots > 0): ?>
                            <span class="available">(<?php echo $available_spots; ?> volných)</span>
                        <?php else: ?>
                            <span class="full">Obsazeno</span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="schedule-item">
                    <strong>Status:</strong>
                    <span class="status-<?php echo $lesson->status; ?>">
                        <?php echo $lesson->status === 'active' ? 'Aktivní' : 'Zrušena'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Edit Lesson Form -->
        <div class="edit-lesson-card">
            <h2>Upravit lekci</h2>

            <form method="post" class="lesson-edit-form">
                <?php wp_nonce_field('mom_admin_action'); ?>
                <input type="hidden" name="mom_action" value="update_lesson">
                <input type="hidden" name="lesson_id" value="<?php echo $lesson->id; ?>">

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="lesson_title">Název lekce</label></th>
                        <td>
                            <input name="title" type="text" id="lesson_title"
                                   class="regular-text"
                                   value="<?php echo esc_attr($lesson->title); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lesson_description">Popis</label></th>
                        <td>
                            <textarea name="description" id="lesson_description"
                                      rows="3" class="large-text"><?php echo esc_textarea($lesson->description ?? ''); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lesson_datetime">Datum a čas</label></th>
                        <td>
                            <input name="date_time" type="datetime-local"
                                   id="lesson_datetime"
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($lesson->date_time)); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lesson_capacity">Kapacita</label></th>
                        <td>
                            <input name="max_capacity" type="number"
                                   id="lesson_capacity" min="1" max="50"
                                   value="<?php echo $lesson->max_capacity; ?>">
                            <p class="description">
                                Aktuálně přihlášeno: <?php echo count($participants); ?> účastníků
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lesson_status">Status</label></th>
                        <td>
                            <select name="status" id="lesson_status">
                                <option value="active" <?php selected($lesson->status, 'active'); ?>>Aktivní</option>
                                <option value="cancelled" <?php selected($lesson->status, 'cancelled'); ?>>Zrušena</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary"
                           value="Uložit změny">
                    <a href="<?php echo admin_url('admin.php?page=mom-booking-admin'); ?>"
                       class="button">Zpět na přehled</a>
                </p>
            </form>
        </div>

        <!-- Participants Management -->
        <div class="participants-card">
            <h2>Účastníci lekce</h2>

            <?php if (!empty($participants)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Jméno</th>
                            <th>Dítě</th>
                            <th>Email</th>
                            <th>Telefon</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $participant): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($participant->customer_name); ?></strong>
                                    <?php if ($participant->customer_id): ?>
                                        <br><small>
                                            <a href="<?php echo admin_url('admin.php?page=mom-user-detail&id=' . $participant->customer_id); ?>">
                                                Zobrazit profil
                                            </a>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($participant->child_name ?: '-'); ?></td>
                                <td><?php echo esc_html($participant->customer_email); ?></td>
                                <td><?php echo esc_html($participant->customer_phone ?: '-'); ?></td>
                                <td>
                                    <button class="button button-small remove-participant"
                                            data-booking-id="<?php echo $participant->id; ?>"
                                            data-customer-name="<?php echo esc_attr($participant->customer_name); ?>">
                                        Odhlásit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Na tuto lekci není nikdo přihlášen.</p>
            <?php endif; ?>
        </div>

        <!-- Add Participant -->
        <?php if (!empty($available_users) && $available_spots > 0): ?>
            <div class="add-participant-card">
                <h2>Přidat účastníka</h2>

                <form method="post" class="add-participant-form">
                    <?php wp_nonce_field('mom_admin_action'); ?>
                    <input type="hidden" name="mom_action" value="add_user_to_lesson">
                    <input type="hidden" name="lesson_id" value="<?php echo $lesson->id; ?>">

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="user_id">Vybrat uživatele</label></th>
                            <td>
                                <select name="user_id" id="user_id" required class="regular-text">
                                    <option value="">-- Vyberte uživatele --</option>
                                    <?php foreach ($available_users as $user): ?>
                                        <option value="<?php echo $user->id; ?>">
                                            <?php echo esc_html($user->name); ?>
                                            <?php if ($user->child_name): ?>
                                                (<?php echo esc_html($user->child_name); ?>)
                                            <?php endif; ?>
                                            - <?php echo esc_html($user->email); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    Zobrazeni jsou pouze uživatelé, kteří ještě nejsou na tuto lekci přihlášeni.
                                </p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="submit" class="button button-primary"
                               value="Přidat na lekci">
                    </p>
                </form>
            </div>
        <?php elseif ($available_spots <= 0): ?>
            <div class="add-participant-card">
                <h2>Přidat účastníka</h2>
                <p><em>Lekce je plně obsazena. Chcete-li přidat dalšího účastníka, nejprve zvyšte kapacitu lekce.</em></p>
            </div>
        <?php else: ?>
            <div class="add-participant-card">
                <h2>Přidat účastníka</h2>
                <p><em>Všichni uživatelé jsou již na tuto lekci přihlášeni nebo nejsou k dispozici žádní uživatelé.</em></p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=mom-users'); ?>" class="button">
                        Vytvořit nového uživatele
                    </a>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Remove participant
    $('.remove-participant').click(function(e) {
        e.preventDefault();

        var bookingId = $(this).data('booking-id');
        var customerName = $(this).data('customer-name');
        var row = $(this).closest('tr');

        if (!confirm('Opravdu chcete odhlásit ' + customerName + ' z této lekce?')) {
            return;
        }

        $.post(ajaxurl, {
            action: 'mom_remove_user_from_lesson',
            booking_id: bookingId,
            nonce: '<?php echo wp_create_nonce('mom_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                row.fadeOut(function() {
                    row.remove();
                    location.reload(); // Refresh to update counts
                });
            } else {
                alert('Chyba: ' + response.data);
            }
        });
    });
});
</script>

<style>
.lesson-detail-container {
    display: grid;
    gap: 20px;
    margin-top: 20px;
}

.lesson-info-card,
.edit-lesson-card,
.participants-card,
.add-participant-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.lesson-schedule {
    display: grid;
    gap: 10px;
}

.schedule-item {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.schedule-item:last-child {
    border-bottom: none;
}

.duration {
    color: #666;
    font-size: 0.9em;
}

.capacity-info .available {
    color: #46b450;
    font-weight: bold;
}

.capacity-info .full {
    color: #dc3545;
    font-weight: bold;
}

.status-active {
    color: #46b450;
    font-weight: bold;
}

.status-cancelled {
    color: #dc3545;
    font-weight: bold;
}

.remove-participant {
    background: #dc3545;
    color: white;
    border-color: #dc3545;
}

.remove-participant:hover {
    background: #c82333;
    border-color: #c82333;
}
</style>
