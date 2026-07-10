<?php
include('header.php');

// Fetch all user groups
$groupsQuery = "SELECT * FROM user_group WHERE deleted = 0 ORDER BY display_order ASC";
$groupsResult = mysqli_query($con, $groupsQuery);

// Fetch all users
$usersQuery = "SELECT u.*, ug.group_name
                FROM user_list u
                LEFT JOIN user_group ug ON u.user_group_id = ug.id
                ORDER BY u.name ASC";
$usersResult = mysqli_query($con, $usersQuery);
?>

<div class="main-panel">
    <div class="main-content" style="clear: left;">
        <div class="content-wrapper">
            <div class="row">
                <div class="col-md-12">
                    <h4>ব্যবহারকারীদের গ্রুপ বরাদ্দ করুন</h4>
                </div>
            </div>

            <section class="invoice-template">
                <div class="card">
                    <div class="card-body p-3">
                        <div id="invoice-template" class="card-block">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="statusMsg"></div>

                                    <table id="userGroupAssignmentTable" class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th width="50">ক্রমিক</th>
                                                <th>ব্যবহারকারীর নাম</th>
                                                <th>ইউজার আইডি</th>
                                                <th>বর্তমান গ্রুপ</th>
                                                <th width="250">গ্রুপ নির্বাচন করুন</th>
                                                <th width="100">কার্যক্রম</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $sl = 1;
                                            mysqli_data_seek($groupsResult, 0); // Reset groups result pointer
                                            while($user = mysqli_fetch_assoc($usersResult)):
                                            ?>
                                                <tr>
                                                    <td><?php echo $sl++; ?></td>
                                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                                    <td>
                                                        <?php echo $user['group_name'] ? htmlspecialchars($user['group_name']) : '<span class="text-muted">কোন গ্রুপ নেই</span>'; ?>
                                                    </td>
                                                    <td>
                                                        <select class="form-control user-group-select" data-user-id="<?php echo $user['dataID']; ?>">
                                                            <option value="">-- গ্রুপ নির্বাচন করুন --</option>
                                                            <?php
                                                            mysqli_data_seek($groupsResult, 0);
                                                            while($group = mysqli_fetch_assoc($groupsResult)):
                                                            ?>
                                                                <option value="<?php echo $group['id']; ?>"
                                                                    <?php echo ($user['user_group_id'] == $group['id']) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($group['group_name']); ?>
                                                                </option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary save-group-btn" data-user-id="<?php echo $user['dataID']; ?>">
                                                            <i class="fa fa-save"></i> সংরক্ষণ
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<style>
.swal-title {
    font-size: 18px !important;
}
.swal-text {
    font-size: 16px !important;
}
.swal-button {
    font-size: 14px !important;
}
</style>

<script>
$(document).ready(function(){
    // Initialize DataTable
    $('#userGroupAssignmentTable').DataTable({
        "order": [[1, "asc"]],
        "language": {
            "processing": "প্রসেসিং...",
            "search": "অনুসন্ধান:",
            "lengthMenu": "প্রদর্শন _MENU_ এন্ট্রি",
            "info": "দেখানো হচ্ছে _START_ থেকে _END_ পর্যন্ত, মোট _TOTAL_ এন্ট্রি",
            "infoEmpty": "কোন এন্ট্রি নেই",
            "infoFiltered": "(মোট _MAX_ এন্ট্রি থেকে ফিল্টার করা হয়েছে)",
            "loadingRecords": "লোড হচ্ছে...",
            "zeroRecords": "কোন রেকর্ড পাওয়া যায়নি",
            "emptyTable": "টেবিলে কোন ডেটা নেই",
            "paginate": {
                "first": "প্রথম",
                "previous": "পূর্ববর্তী",
                "next": "পরবর্তী",
                "last": "শেষ"
            }
        }
    });

    // Save group assignment
    $('.save-group-btn').on('click', function() {
        const userId = $(this).data('user-id');
        const groupId = $(`.user-group-select[data-user-id="${userId}"]`).val();
        const btn = $(this);

        $.ajax({
            type: 'POST',
            url: '../../api/users/save-assignment.php',
            data: {
                user_id: userId,
                group_id: groupId
            },
            dataType: 'json',
            beforeSend: function() {
                btn.attr('disabled', 'disabled').html('<i class="fa fa-spinner fa-spin"></i> সংরক্ষণ হচ্ছে...');
            },
            success: function(response) {
                if (response.status == 1) {
                    swal("সফল!", response.message, "success").then(() => {
                        location.reload();
                    });
                } else {
                    swal("ত্রুটি!", response.message, "error");
                }
                btn.removeAttr('disabled').html('<i class="fa fa-save"></i> সংরক্ষণ');
            },
            error: function() {
                swal("ত্রুটি!", "গ্রুপ বরাদ্দ করতে ব্যর্থ হয়েছে!", "error");
                btn.removeAttr('disabled').html('<i class="fa fa-save"></i> সংরক্ষণ');
            }
        });
    });
});
</script>
