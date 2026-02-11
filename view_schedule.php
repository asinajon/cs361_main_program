<?php
session_start();

// Redirect to login if the user isn't logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require('mysqli_connect.php'); 
$user_id = $_SESSION['user_id']; // This is your link to the DB
echo $_SESSION['user_id'];
// 2. RETRIEVE 'COMPARE' SELECTIONS FROM URL
// This identifies which showtimes the user is currently "drafting"
// 2. HANDLE POST ACTIONS (Comparison + New Insertion)
$selected_ids = $_POST['compare'] ?? [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_new'])) {
    // Loop through the arrays to find which day had data entered
    foreach ($_POST['new_title'] as $day_key => $title) {
        if (!empty($title)) {
            $date      = $_POST['add_date'][$day_key];
            $time      = $_POST['new_time'][$day_key];
            $dur       = (int)$_POST['new_duration'][$day_key];
            $loc       = mysqli_real_escape_string($dbc, $_POST['new_location'][$day_key]);
            $title_esc = mysqli_real_escape_string($dbc, $title);

            $insert_q = "INSERT INTO movie_schedule (user_id, movie_title, show_date, show_time, duration, location) 
                         VALUES ($user_id, '$title_esc', '$date', '$time', $dur, '$loc')";
            mysqli_query($dbc, $insert_q);
        }
    }
    // Refresh to show the new movie and clear POST data
    header("Location: view_schedule.php");
    exit();
}
// 3. FETCH ALL MOVIES FOR THE CURRENT WEEK
$q = "SELECT id, movie_title, show_date, show_time, duration, location, 
      DATE_FORMAT(show_date, '%W') AS day_of_week 
      FROM movie_schedule 
      WHERE user_id = $user_id
      ORDER BY show_date ASC, show_time ASC"; // Ensures chronological order per day

$r = mysqli_query($dbc, $q);

// Organize results into an array grouped by day for easy grid rendering
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$schedule = array_fill_keys($days, []);

while ($row = mysqli_fetch_array($r, MYSQLI_ASSOC)) {
    $schedule[$row['day_of_week']][] = $row;
}

// Helper function for 12-hour formatting
function formatTime($time) {
    return date("g:i A", strtotime($time));
}

$page_title = "This Week's Movie Schedule";
include ('includes/header.html');
?>

<div class="container">
    <h1>Weekly Movie Schedule</h1>
    
    <form action="view_schedule.php" method="POST">
        <table>
            <thead>
                <tr><?php foreach ($days as $day) echo "<th>$day</th>"; ?></tr>
            </thead>
            <tbody>
                <tr>
                    <?php foreach ($days as $day): ?>
                        <td>
                            <?php if (!empty($schedule[$day])): ?>
                                <?php foreach ($schedule[$day] as $movie): ?>
                                    <?php 
                                        $is_selected = in_array($movie['id'], $selected_ids);
                                        $class = (empty($selected_ids) || $is_selected) ? 'selected-box' : 'standard-box';
                                    ?>
                                    <div class="movie-entry <?php echo $class; ?>">
                                        <input type="checkbox" name="compare[]" value="<?php echo $movie['id']; ?>" 
                                               <?php if($is_selected) echo 'checked'; ?>>
                                        <span><?php echo formatTime($movie['show_time']); ?></span>
                                        <span><strong><?php echo htmlspecialchars($movie['movie_title']); ?></strong></span>
                                        <span><?php echo htmlspecialchars($movie['location']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <div class="quick-add">
                                <small><strong>Add to <?php echo $day; ?></strong></small>
                                <input type="hidden" name="add_date[<?php echo $day; ?>]" 
                                    value="<?php echo date('Y-m-d', strtotime("this $day")); ?>">
                                
                                <input type="text" name="new_title[<?php echo $day; ?>]" placeholder="Film Title">
                                <input type="time" name="new_time[<?php echo $day; ?>]">
                                
                                <div style="display: flex; gap: 2%; width: 95%;">
                                    <input type="text" name="new_location[<?php echo $day; ?>]" placeholder="Theater" style="width: 60%;">
                                    <input type="number" name="new_duration[<?php echo $day; ?>]" placeholder="Min" style="width: 38%;">
                                </div>
                            </div>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>

        <div class="btn-row">
            <input type="submit" name="update_compare" value="Update Comparison View">
            <input type="submit" name="submit_new" value="Save New Entries" class="btn-save">
            <a href="view_schedule.php" style="margin-left:15px; font-size: 0.9rem;">Clear Selections</a>
        </div>
    </form>
</div>

</html>