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
// $selected_ids = $_POST['compare'] ?? [];

// if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_new'])) {
//     // Loop through the arrays to find which day had data entered
//     foreach ($_POST['new_title'] as $day_key => $title) {
//         if (!empty($title)) {
//             $date      = $_POST['add_date'][$day_key];
//             $time      = $_POST['new_time'][$day_key];
//             $dur       = (int)$_POST['new_duration'][$day_key];
//             $loc       = mysqli_real_escape_string($dbc, $_POST['new_location'][$day_key]);
//             $title_esc = mysqli_real_escape_string($dbc, $title);

//             $insert_q = "INSERT INTO movie_schedule (user_id, title, show_date, show_time, duration, location) 
//                          VALUES ($user_id, '$title_esc', '$date', '$time', $dur, '$loc')";
//             mysqli_query($dbc, $insert_q);
//         }
//     }
//     // Refresh to show the new movie and clear POST data
//     header("Location: view_schedule.php");
//     exit();
// }
// $current_region = 'US';
// $current_length = 'short';
// $user_id = $_SESSION['user_id'];
$fetch_date_prefs = "SELECT date_region, date_length FROM users WHERE user_id = ?";
$fetch_stmt = $dbc->prepare($fetch_date_prefs);
$fetch_stmt->bind_param("i", $user_id);
$fetch_stmt->execute();
$fetch_result = $fetch_stmt->get_result();
$row = $fetch_result->fetch_assoc();
$user_region = $row['date_region'];
$user_length = $row['date_length'];

$raw_dates = [];
$date = new DateTime("monday this week");
for ($x = 0; $x <= 6; $x++){
    $raw_dates[] = $date->format('Y-m-d');
    $date->modify('+1 day');
}

$date_info_out = array(
    'format' => [$user_region, $user_length],
    'dates' => $raw_dates
);

$json_date_info_out = json_encode($date_info_out);

$ch = curl_init('http://127.0.0.1:5005/format_dates');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_date_info_out);
$headers = [
    'Content-Type: application/json'
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);

if ($response === false) {
    $formatted_dates = $raw_dates;
} else {
    $formatted_dates = json_decode($response, true);
}

curl_close($ch);
// $find_title_q = "SELECT movie_id, duration_minutes, rating FROM movies WHERE title = '$title_esc'";
// $find_title = mysql_query($dbc, $find_title_q);
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (isset($_POST['update_prefs'])) {
        $new_region = $_POST['date_region'];
        $new_length = $_POST['date_length'];

        $update_date_pref = "UPDATE users SET date_region = ?, date_length = ? WHERE user_id = ?";
        $update_stmt = $dbc->prepare($update_date_pref);
        $update_stmt->bind_param("ssi", $new_region, $new_length, $_SESSION['user_id']);
        $update_stmt->execute();

        $_SESSION['date_region'] = $new_region;
        $_SESSION['date_length'] = $new_length;

        header("Location: view_schedule.php");
        exit();
    }

    if (isset($_POST['submit_new'])) {
        foreach($_POST['new_title'] as $day_key => $title) {
            if (!empty($title)) {
                $title_esc = mysqli_real_escape_string($dbc, $title);
                $find_title_q = "SELECT movie_id, duration_minutes, rating FROM movies WHERE title = '$title_esc'";
                $find_title = mysqli_query($dbc, $find_title_q);
                if (mysqli_num_rows($find_title_r) > 0) {
                    echo "DEBUG: Movie Found! <br>";
                    // SCENARIO A: Movie exists in the library
                    $row = mysqli_fetch_array($find_title_r, MYSQLI_ASSOC);
                    $movie_id = $row['movie_id'];
                    $dur      = $row['duration_minutes'];
                    $rating   = $row['rating'];
                } else {
                    echo "DEBUG: Movie NOT Found. Creating new entry… <br>";
                    // SCENARIO B: Movie is new, must be added to library
                    $dur          = (int)$_POST['new_duration'][$day_key];
                    $rating       = $_POST['new_rating'][$day_key];
                    $release_year = (int)$_POST['new_release_year'][$day_key];

                    $insert_movie_q = "INSERT INTO movies (title, duration_minutes, rating, release_year) 
                                    VALUES ('$title_esc', $dur, '$rating', $release_year)";
                    mysqli_query($dbc, $insert_movie_q);
                    
                    $movie_id = mysqli_insert_id($dbc);
                }
                echo "Debug: Proceeding with Movie ID: $movie_id, Rating: $rating, Duration: $dur <br><hr>";
            }
        }
    }
}

// 3. FETCH ALL MOVIES FOR THE CURRENT WEEK
// $q = "SELECT id, movie_title, show_date, show_time, duration, location, 
//       DATE_FORMAT(show_date, '%W') AS day_of_week 
//       FROM movie_schedule 
//       WHERE user_id = $user_id
//       ORDER BY show_date ASC, show_time ASC"; // Ensures chronological order per day

// $r = mysqli_query($dbc, $q);

// Organize results into an array grouped by day for easy grid rendering
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$schedule = array_fill_keys($days, []);

// while ($row = mysqli_fetch_array($r, MYSQLI_ASSOC)) {
//     $schedule[$row['day_of_week']][] = $row;
// }

// Helper function for 12-hour formatting
function formatTime($time) {
    return date("g:i A", strtotime($time));
}

$page_title = "This Week's Movie Schedule";
include ('includes/header.html');
?>

<div class="container">
    <h1>Weekly Movie Schedule</h1>
    <form method="POST" action="view_schedule.php" class="settings-bar">
        <label>Region:</label>
        <select name="date_region">
            <option value="US" <?php if($user_region == 'US') echo 'selected'; ?>>US</option>
            <option value="International" <?php if($user_region =='International') echo 'selected'; ?>>International</option>
        </select>
        <label>Date Length</label>
        <select name="date_length">
            <option value="Short" <?php if($user_length == 'short') echo 'selected'; ?>>Short</option>
            <option value="Long" <?php if($user_length == 'long') echo 'selected'; ?>>Long</option>
        </select> 
        <input type="submit" name="update_prefs" value="Update View" class="btn-settings">
    </form>
    <form action="view_schedule.php" method="POST">
        <table>
            <thead>
                <tr><?php foreach ($formatted_dates as $index => $date_string): ?>
                    <th>
                        <?php echo $days[$index]; ?><br>
                        <?php echo $date_string; ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
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
                                    value="<?php echo $raw_dates[$index]; ?>">
                                
                                <input type="text" name="new_title[<?php echo $day; ?>]" placeholder="Film Title" required>
                                <input type="time" name="new_time[<?php echo $day; ?>]" required>
                                <small><strong>Rating</strong></small>
                                <select name="new_rating[<?php echo $day; ?>]">
                                    <option value="G">G</option>
                                    <option value="PG">PG</option>
                                    <option value="PG-13">PG-13</option>
                                    <option value="R">R</option>
                                    <option value="NR">NR</option>
                                </select>
                                <small><strong>Preshow</strong></small>
                                <select name="new_preshow[<?php echo $day; ?>]">
                                    <option value="0">0 min</option>
                                    <option value="10">10 min</option>
                                    <option value="15" selected>15 min</option>
                                    <option value="20">20 min</option>
                                    <option value="25">25 min</option>
                                </select>


                                <input type="text" name="new_location[<?php echo $day; ?>]" placeholder="Theater" style="width: 100%;">
                                
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <div style="display: flex; gap: 4%;">
                                        <input type="number" name="new_duration[<?php echo $day; ?>]" placeholder="Min" style="width: 38%; padding: 4px;">
                                        <input type="number" name="new_release_year[<?php echo $day; ?>]" placeholder="Year" style="width: 38%; padding: 4px;">
                                    </div>
                                    <input type="number" step="0.01" name="new_cost[<?php echo $day; ?>]" placeholder="Cost $" style="width: 50%; border: 1px solid #ccc;">
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