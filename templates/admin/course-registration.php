<?php
/**
 * Template: Course Registration Page
 */
?>

<div class="wrap">
    <h1>Registrace na kurz: <?php echo esc_html($course->title); ?></h1>

    <div class="course-registration-container">
        <!-- Course Info -->
        <div class="course-info-card">
            <h2>Informace o kurzu</h2>
            <p><strong>Začátek:</strong> <?php echo date('d.m.Y', strtotime($course->start_date)); ?></p>
            <p><strong>Počet lekcí:</strong> <?php echo $course->lesson_count; ?></p>
            <p><strong>Kapacita:</strong> <?php echo $course->max_capacity; ?> na lekci</p>
            <p><strong>Obsazenost:</strong> <?php echo $course_stats['occupancy_rate']; ?>%</p>
        </div>

        <!-- Register New User -->
        <div class="register-user-card">
            <h2>Registrovat uživatele na kurz</h2>

            <form method="post">
                <?php wp_nonce_field('mom_admin_action'); ?>
                <input type="hidden" name="mom_action" value="register_user_for_course">
                <input type="hidden" name="course_id" value="<?php echo $course->id; ?>">

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="user_id">Vybrat uživatele</label></th>
                        <td>
                            <select name="user_id" id="user_id" required class="regular-text">
                                <option value="">-- Vyberte uživatele --</option>
                                <?php
                                $registered_emails = array_column($registered_users, 'email');
                                foreach ($all_users as $user):
                                    if (!in_array($user->email, $registered_emails)):
                                ?>
                                    <option value="<?php echo $user->id; ?>">
                                        <?php echo esc_html($user->name); ?>
                                        <?php if ($user->child_name): ?>
                                            (<?php echo esc_html($user->child_name); ?>)
                                        <?php endif; ?>
                                        - <?php echo esc_html($user->email); ?>
                                    </option>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="Registrovat na kurz">
                </p>
            </form>
        </div>

        <!-- Registered Users -->
        <div class="registered-users-card">
            <h2>Registrovaní uživatelé (<?php echo count($registered_users); ?>)</h2>

            <?php if (!empty($registered_users)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Jméno</th>
                            <th>Dítě</th>
                            <th>Email</th>
                            <th>Registrovaných lekcí</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registered_users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($user->name); ?></strong>
                                    <br><small>
                                        <a href="<?php echo admin_url('admin.php?page=mom-user-detail&id=' . $user->id); ?>">
                                            Zobrazit detail
                                        </a>
                                    </small>
                                </td>
                                <td><?php echo esc_html($user->child_name ?: '-'); ?></td>
                                <td><?php echo esc_html($user->email); ?></td>
                                <td><?php echo $user->lessons_booked; ?>/<?php echo $course->lesson_count; ?></td>
                                <td>
                                    <button class="button button-small unregister-user"
                                            data-user-id="<?php echo $user->id; ?>"
                                            data-course-id="<?php echo $course->id; ?>"
                                            data-user-name="<?php echo esc_attr($user->name); ?>">
                                        Odhlásit z kurzu
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Na kurz není registrován žádný uživatel.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('.unregister-user').click(function(e) {
        e.preventDefault();

        var userId = $(this).data('user-id');
        var courseId = $(this).data('course-id');
        var userName = $(this).data('user-name');
        var row = $(this).closest('tr');

        if (!confirm('Opravdu chcete odhlásit ' + userName + ' z celého kurzu?')) {
            return;
        }

        $.post(ajaxurl, {
            action: 'mom_unregister_user_from_course',
            user_id: userId,
            course_id: courseId,
            nonce: '<?php echo wp_create_nonce('mom_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                row.fadeOut(function() {
                    row.remove();
                });
                alert('Uživatel byl odhlášen z kurzu.');
            } else {
                alert('Chyba: ' + response.data);
            }
        });
    });
});
</script>

<style>
.course-registration-container {
    display: grid;
    gap: 20px;
    margin-top: 20px;
}

.course-info-card,
.register-user-card,
.registered-users-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.unregister-user {
    background: #dc3545;
    color: white;
    border-color: #dc3545;
}

.unregister-user:hover {
    background: #c82333;
    border-color: #c82333;
}
</style>
