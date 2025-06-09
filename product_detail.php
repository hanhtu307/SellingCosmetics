<?php
session_start();
require_once "connect.php"; // File kết nối database

// Kết nối CSDL
$conn = mysqli_connect("localhost", "root", "", "db_mypham");
mysqli_set_charset($conn, "utf8");

// Kiểm tra lỗi kết nối
if (!$conn) {
    die("Kết nối CSDL thất bại: " . mysqli_connect_error());
}

// Lấy ID từ URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sql = "SELECT * FROM product WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    echo "Không tìm thấy sản phẩm!";
    exit;
}

// Tạo token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Kiểm tra xem người dùng có mua sản phẩm và đơn hàng đã thanh toán hay không
$can_review = false;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT o.id FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'Đã thanh toán'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) > 0) {
        $can_review = true;
    }
    mysqli_stmt_close($stmt);
}

// Kiểm tra và lấy username của người dùng hiện tại
$current_user = null;
if (isset($_SESSION['user_id'])) {
    $sql = "SELECT username FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $current_user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Xử lý gửi, chỉnh sửa, xóa đánh giá và trả lời
$comment_success = false;
$comment_error = '';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Kiểm tra CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $comment_error = "Yêu cầu không hợp lệ.";
    } elseif (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    } else {
        $user_id = $_SESSION['user_id'];

        // Xử lý trả lời đánh giá
        if (isset($_POST['reply_to_review_id'])) {
            $review_id = intval($_POST['reply_to_review_id']);
            $reply_content = trim($_POST['reply_content']);

            if (empty($reply_content)) {
                $comment_error = "Vui lòng nhập nội dung trả lời.";
            } else {
                $sql = "INSERT INTO review_replies (review_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iis", $review_id, $user_id, $reply_content);
                if (mysqli_stmt_execute($stmt)) {
                    $comment_success = true;
                    header("Location: product_detail.php?id=$id");
                    exit();
                } else {
                    $comment_error = "Lỗi khi gửi trả lời: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        }
        // Xử lý xóa trả lời
        elseif (isset($_POST['delete_reply_id'])) {
            $delete_id = intval($_POST['delete_reply_id']);
            $sql = "DELETE FROM review_replies WHERE id = ? AND user_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $delete_id, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $comment_success = true;
                header("Location: product_detail.php?id=$id");
                exit();
            } else {
                $comment_error = "Lỗi khi xóa trả lời: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
        // Xử lý chỉnh sửa trả lời
        elseif (isset($_POST['edit_reply_id'])) {
            $edit_id = intval($_POST['edit_reply_id']);
            $new_content = trim($_POST['edit_reply_content']);

            if (empty($new_content)) {
                $comment_error = "Vui lòng nhập nội dung trả lời.";
            } else {
                $sql = "UPDATE review_replies SET content = ? WHERE id = ? AND user_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sii", $new_content, $edit_id, $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $comment_success = true;
                    header("Location: product_detail.php?id=$id");
                    exit();
                } else {
                    $comment_error = "Lỗi khi chỉnh sửa trả lời: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        }
        // Xử lý xóa đánh giá
        elseif (isset($_POST['delete_review_id'])) {
            $delete_id = intval($_POST['delete_review_id']);
            $sql = "DELETE FROM product_reviews WHERE id = ? AND user_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $delete_id, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $comment_success = true;
                header("Location: product_detail.php?id=$id");
                exit();
            } else {
                $comment_error = "Lỗi khi xóa đánh giá: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
        // Xử lý chỉnh sửa đánh giá
        elseif (isset($_POST['edit_review_id'])) {
            $edit_id = intval($_POST['edit_review_id']);
            $new_comment = trim($_POST['edit_comment']);

            if (empty($new_comment)) {
                $comment_error = "Vui lòng nhập nội dung đánh giá.";
            } else {
                $sql = "UPDATE product_reviews SET comment = ? WHERE id = ? AND user_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sii", $new_comment, $edit_id, $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $comment_success = true;
                    header("Location: product_detail.php?id=$id");
                    exit();
                } else {
                    $comment_error = "Lỗi khi chỉnh sửa đánh giá: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        }
        // Xử lý gửi đánh giá mới
        elseif (isset($_POST['submit_review'])) {
            $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
            $comment = trim($_POST['comment']);

            if ($rating < 1 || $rating > 5) {
                $comment_error = "Vui lòng chọn số sao từ 1 đến 5.";
            } elseif (empty($comment)) {
                $comment_error = "Vui lòng nhập nội dung đánh giá.";
            } else {
                $sql = "INSERT INTO product_reviews (product_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iiis", $id, $user_id, $rating, $comment);
                if (mysqli_stmt_execute($stmt)) {
                    $comment_success = true;
                    header("Location: product_detail.php?id=$id");
                    exit();
                } else {
                    $comment_error = "Lỗi khi gửi đánh giá: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Lấy danh sách đánh giá
$sql = "SELECT r.*, u.username FROM product_reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$reviews_result = mysqli_stmt_get_result($stmt);
$reviews = [];
while ($row = mysqli_fetch_assoc($reviews_result)) {
    $reviews[] = $row;
}
mysqli_stmt_close($stmt);

// Lấy danh sách trả lời
$replies = [];
foreach ($reviews as $review) {
    $sql = "SELECT rr.*, u.username FROM review_replies rr JOIN users u ON rr.user_id = u.id WHERE rr.review_id = ? ORDER BY rr.created_at ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $review['id']);
    mysqli_stmt_execute($stmt);
    $replies_result = mysqli_stmt_get_result($stmt);
    $replies[$review['id']] = [];
    while ($row = mysqli_fetch_assoc($replies_result)) {
        $replies[$review['id']][] = $row;
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - Luna Beauty</title>
    <link rel="stylesheet" href="assets/css/product.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <div class="image-section">
            <div class="main-image">
                <img id="mainProductImage" src="assets/<?= htmlspecialchars($product['product_image'] ?? 'default.jpg') ?>" alt="<?= htmlspecialchars($product['name'] ?? 'Sản phẩm') ?>">
            </div>
            <div class="thumbnail-list" id="thumbnailList">
                <!-- Thumbnails sẽ được thêm động qua JavaScript -->
                <img src="assets/<?= htmlspecialchars($product['product_images2'] ?? 'default.jpg') ?>" onclick="changeImage(this)" alt="<?= htmlspecialchars($product['name'] ?? 'Sản phẩm') ?>">
                <img src="assets/<?= htmlspecialchars($product['product_images3'] ?? 'default.jpg') ?>" onclick="changeImage(this)" alt="<?= htmlspecialchars($product['name'] ?? 'Sản phẩm') ?>">
            </div>
            <div class="product-details">
                <h3>Mô tả sản phẩm</h3>
                <p><?= nl2br(htmlspecialchars($product['description'] ?? 'Không có mô tả cho sản phẩm này.')) ?></p>
            </div>
            <div class="comment-section">
    <h3>Đánh giá sản phẩm</h3>
    <?php if ($comment_success): ?>
        <div class="toast success">Thao tác thành công!</div>
        <script>
            setTimeout(() => {
                document.querySelector('.toast').remove();
            }, 3000);
        </script>
    <?php endif; ?>
    <?php if ($comment_error): ?>
        <div class="toast error"><?= htmlspecialchars($comment_error) ?></div>
        <script>
            setTimeout(() => {
                document.querySelector('.toast').remove();
            }, 3000);
        </script>
    <?php endif; ?>
    <?php if (isset($_SESSION['user_id']) && $can_review): ?>
        <form method="POST" class="comment-form">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="star-rating">
                <span class="star" data-value="1"><i class="fas fa-star"></i></span>
                <span class="star" data-value="2"><i class="fas fa-star"></i></span>
                <span class="star" data-value="3"><i class="fas fa-star"></i></span>
                <span class="star" data-value="4"><i class="fas fa-star"></i></span>
                <span class="star" data-value="5"><i class="fas fa-star"></i></span>
                <input type="hidden" name="rating" id="rating" value="0">
            </div>
            <textarea name="comment" placeholder="Viết đánh giá của bạn..." required></textarea>
            <button type="submit" name="submit_review">Gửi đánh giá</button>
        </form>
    <?php elseif (isset($_SESSION['user_id'])): ?>
        <p>Bạn cần mua và thanh toán sản phẩm này để có thể gửi đánh giá.</p>
    <?php else: ?>
        <p>Vui lòng <a href="login.php">đăng nhập</a> và mua sản phẩm để gửi đánh giá.</p>
    <?php endif; ?>
    <div class="comment-list">
        <?php if (count($reviews) > 0): ?>
            <?php foreach ($reviews as $review): ?>
                <!-- Giữ nguyên phần hiển thị đánh giá và trả lời -->
                <div class="comment">
                    <div class="comment-header">
                        <span class="username"><?= htmlspecialchars($review['username']) ?></span>
                        <span class="date"><?= date('d/m/Y H:i', strtotime($review['created_at'])) ?></span>
                    </div>
                    <div class="comment-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= $review['rating'] ? 'filled' : '' ?>"><i class="fas fa-star"></i></span>
                        <?php endfor; ?>
                    </div>
                    <div class="comment-content"><?= nl2br(htmlspecialchars($review['comment'])) ?></div>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <button class="dots-menu-btn" onclick="toggleActionsMenu(<?= $review['id'] ?>)">...</button>
                        <div class="actions-menu" id="actions-menu-<?= $review['id'] ?>">
                            <?php if ($review['user_id'] == $_SESSION['user_id']): ?>
                                <button class="edit-comment-btn" onclick="showEditForm(<?= $review['id'] ?>, '<?= htmlspecialchars(addslashes($review['comment'])) ?>')">Chỉnh sửa</button>
                                <form method="POST" class="delete-comment-form" onsubmit="return confirm('Bạn có chắc muốn xóa bình luận này?');" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="delete_review_id" value="<?= $review['id'] ?>">
                                    <button type="submit" class="delete-comment-btn">Xóa</button>
                                </form>
                            <?php else: ?>
                                <button class="reply-comment-btn" onclick="showReplyForm(<?= $review['id'] ?>)">Trả lời</button>
                            <?php endif; ?>
                        </div>
                        <form method="POST" class="edit-comment-form" id="edit-form-<?= $review['id'] ?>" style="display: none;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <textarea name="edit_comment" required><?= htmlspecialchars($review['comment']) ?></textarea>
                            <input type="hidden" name="edit_review_id" value="<?= $review['id'] ?>">
                            <button type="submit">Lưu</button>
                            <button type="button" onclick="hideEditForm(<?= $review['id'] ?>)">Hủy</button>
                        </form>
                        <form method="POST" class="reply-input" id="reply-form-<?= $review['id'] ?>" style="display: none;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <textarea name="reply_content" placeholder="Viết trả lời của bạn..." required></textarea>
                            <input type="hidden" name="reply_to_review_id" value="<?= $review['id'] ?>">
                            <button type="submit" class="post-reply-btn">Đăng</button>
                        </form>
                    <?php endif; ?>
                    <?php if (!empty($replies[$review['id']])): ?>
                        <div class="replies">
                            <?php foreach ($replies[$review['id']] as $reply): ?>
                                <div class="reply">
                                    <div class="reply-header">
                                        <span class="username"><?= htmlspecialchars($reply['username']) ?></span>
                                        <span class="date"><?= date('d/m/Y H:i', strtotime($reply['created_at'])) ?></span>
                                    </div>
                                    <div class="reply-content"><?= nl2br(htmlspecialchars($reply['content'])) ?></div>
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                        <button class="dots-menu-btn" onclick="toggleReplyActionsMenu(<?= $reply['id'] ?>)">...</button>
                                        <div class="actions-menu" id="reply-actions-menu-<?= $reply['id'] ?>">
                                            <?php if ($reply['user_id'] == $_SESSION['user_id']): ?>
                                                <button class="edit-reply-btn" onclick="showEditReplyForm(<?= $reply['id'] ?>, '<?= htmlspecialchars(addslashes($reply['content'])) ?>')">Chỉnh sửa</button>
                                                <form method="POST" class="delete-reply-form" onsubmit="return confirm('Bạn có chắc muốn xóa trả lời này?');" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="delete_reply_id" value="<?= $reply['id'] ?>">
                                                    <button type="submit" class="delete-reply-btn">Xóa</button>
                                                </form>
                                            <?php else: ?>
                                                <button class="reply-comment-btn" onclick="showReplyForm(<?= $review['id'] ?>, <?= $reply['id'] ?>)">Trả lời</button>
                                            <?php endif; ?>
                                        </div>
                                        <form method="POST" class="edit-reply-form" id="edit-reply-form-<?= $reply['id'] ?>" style="display: none;">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <textarea name="edit_reply_content" required><?= htmlspecialchars($reply['content']) ?></textarea>
                                            <input type="hidden" name="edit_reply_id" value="<?= $reply['id'] ?>">
                                            <button type="submit">Lưu</button>
                                            <button type="button" onclick="hideEditReplyForm(<?= $reply['id'] ?>)">Hủy</button>
                                        </form>
                                        <form method="POST" class="reply-input" id="reply-form-<?= $review['id'] ?>-<?= $reply['id'] ?>" style="display: none;">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <textarea name="reply_content" placeholder="Viết trả lời của bạn..." required></textarea>
                                            <input type="hidden" name="reply_to_review_id" value="<?= $review['id'] ?>">
                                            <button type="submit" class="post-reply-btn">Đăng</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Chưa có đánh giá nào cho sản phẩm này.</p>
        <?php endif; ?>
    </div>
</div>
        </div>
        <div class="info-section">
            <h1><?= htmlspecialchars($product['name']) ?></h1>
            <p><strong>Danh mục:</strong> <?= htmlspecialchars($product['category']) ?></p>
            <p class="price"><?= number_format($product['price'], 0, ',', '.') ?>₫</p>
            <p class="stock">Còn hàng: <?= htmlspecialchars($product['stock']) ?> sản phẩm</p>
            <!-- Thêm phần phân loại sản phẩm -->
            <div class="product-variants">
                <h4>Phân Loại</h4>
                <div class="variant-options" id="variant-options-page"></div>
            </div>
            <form method="POST" action="add_to_cart.php" class="cart-form">
                <input type="hidden" name="product_id" value="<?= $id ?>">
                <input type="hidden" name="product_name" value="<?= htmlspecialchars($product['name']) ?>">
                <input type="hidden" name="product_price" id="cartPrice" value="<?= $product['price'] ?>">
                <input type="hidden" name="product_img" id="cartImg" value="<?= htmlspecialchars($product['product_image']) ?>">
                <input type="hidden" name="product_option" id="cartOption" value="">
                <div class="quantity-box">
                    <button type="button" class="qty-btn" onclick="changeQty(-1)">−</button>
                    <input type="text" name="product_qty" id="product_qty" value="1" readonly>
                    <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
                </div>
                <div class="actions">
                    <button type="button" class="btn cart" onclick="openCartModal('<?= htmlspecialchars($product['name']) ?>', <?= $product['price'] ?>, '<?= htmlspecialchars($product['product_image']) ?>', <?= $product['id'] ?>)">
                        <i class="fas fa-cart-plus"></i> Thêm vào giỏ
                    </button>
                    <button type="submit" form="buyNowForm" class="btn buy"><i class="fas fa-credit-card"></i> Mua ngay</button>
                </div>
            </form>
            <form method="POST" action="checkout.php" id="buyNowForm">
                <input type="hidden" name="items[0][product_id]" value="<?= $id ?>">
                <input type="hidden" name="items[0][product_name]" value="<?= htmlspecialchars($product['name']) ?>">
                <input type="hidden" name="items[0][product_price]" id="buyNowPrice" value="<?= $product['price'] ?>">
                <input type="hidden" name="items[0][product_option]" id="buyNowOption" value="">
                <input type="hidden" name="items[0][product_img]" id="buyNowImg" value="<?= htmlspecialchars($product['product_image']) ?>">
                <input type="hidden" name="items[0][quantity]" id="buy_now_qty" value="1">
            </form>
            <div class="payment-methods">
                <p><strong>Phương thức thanh toán:</strong></p>
                <img src="data:image/png;base64,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" alt="Visa">
                <img src="https://homepage.momocdn.net/fileuploads/svg/momo-file-240411162904.svg" alt="MoMo">
                <img src="data:image/png;base64,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" alt="VNPay">
            </div>
        </div>
    </div>
    <div class="cart-modal" id="cartModal">
        <div class="cart-modal-content">
            <span class="close-btn" onclick="closeCartModal()">×</span>
            <div class="modal-header">
                <img id="modalProductImg" src="assets/<?= htmlspecialchars($product['product_image']) ?>" alt="Sản phẩm" class="modal-img">
                <div class="product-info">
                    <h3 id="modalTitle"><?= htmlspecialchars($product['name']) ?></h3>
                    <p class="modal-price" id="modalPrice"><?= number_format($product['price'], 0, ',', '.') ?>₫</p>
                    <p class="modal-stock">Kho: <?= htmlspecialchars($product['stock']) ?></p>
                </div>
            </div>
            <form method="POST" action="add_to_cart.php" id="addToCartForm">
                <input type="hidden" name="product_id" value="<?= $id ?>">
                <input type="hidden" name="product_name" id="modalProductName" value="<?= htmlspecialchars($product['name']) ?>">
                <input type="hidden" name="product_price" id="modalProductPrice" value="<?= $product['price'] ?>">
                <input type="hidden" name="product_quantity" id="modalProductQty" value="1">
                <input type="hidden" name="product_option" id="modalProductOption">
                <div class="modal-options">
                    <h4>Phân Loại</h4>
                    <div class="option-btn-group" id="variant-options"></div>
                </div>
                <div class="modal-qty-control">
                    <h4>Số lượng</h4>
                    <div class="quantity-box">
                        <button type="button" class="qty-btn-modal" onclick="decreaseQty()">−</button>
                        <input type="text" id="quantity" value="1" readonly>
                        <button type="button" class="qty-btn-modal" onclick="increaseQty()">+</button>
                    </div>
                </div>
                <button type="submit" class="modal-action-btn">Thêm vào giỏ hàng</button>
            </form>
        </div>
    </div>
    <script>
        // Hàm cho menu ba chấm của bình luận
        function toggleActionsMenu(reviewId) {
            const menu = document.getElementById(`actions-menu-${reviewId}`);
            const isActive = menu.classList.contains('active');
            // Đóng tất cả menu
            document.querySelectorAll('.actions-menu').forEach(m => m.classList.remove('active'));
            // Mở/đóng menu hiện tại
            if (!isActive) {
                menu.classList.add('active');
            }
        }

        // Hàm cho menu ba chấm của trả lời
        function toggleReplyActionsMenu(replyId) {
            const menu = document.getElementById(`reply-actions-menu-${replyId}`);
            const isActive = menu.classList.contains('active');
            // Đóng tất cả menu
            document.querySelectorAll('.actions-menu').forEach(m => m.classList.remove('active'));
            // Mở/đóng menu hiện tại
            if (!isActive) {
                menu.classList.add('active');
            }
        }

        // Đóng menu khi nhấn ra ngoài
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dots-menu-btn') && !e.target.closest('.actions-menu')) {
                document.querySelectorAll('.actions-menu').forEach(m => m.classList.remove('active'));
            }
        });

        // Hàm cho chỉnh sửa bình luận
        function showEditForm(reviewId, comment) {
            document.getElementById(`edit-form-${reviewId}`).style.display = 'flex';
            document.getElementById(`actions-menu-${reviewId}`).classList.remove('active');
        }

        function hideEditForm(reviewId) {
            document.getElementById(`edit-form-${reviewId}`).style.display = 'none';
        }

        // Hàm cho chỉnh sửa trả lời
        function showEditReplyForm(replyId, content) {
            document.getElementById(`edit-reply-form-${replyId}`).style.display = 'flex';
            document.getElementById(`reply-actions-menu-${replyId}`).classList.remove('active');
        }

        function hideEditReplyForm(replyId) {
            document.getElementById(`edit-reply-form-${replyId}`).style.display = 'none';
        }

        // Hàm cho trả lời
        function showReplyForm(reviewId, replyId = null) {
            const formId = replyId ? `reply-form-${reviewId}-${replyId}` : `reply-form-${reviewId}`;
            document.getElementById(formId).style.display = 'flex';
            if (replyId) {
                document.getElementById(`reply-actions-menu-${replyId}`).classList.remove('active');
            } else {
                document.getElementById(`actions-menu-${reviewId}`).classList.remove('active');
            }
        }

        function hideReplyForm(reviewId, replyId = null) {
            const formId = replyId ? `reply-form-${reviewId}-${replyId}` : `reply-form-${reviewId}`;
            document.getElementById(formId).style.display = 'none';
        }

        const productVariants = {
            // skicare
            1: [{
                    name: '130g trắng',
                    img: 'assets/images/p11.jpg',
                    price: 40000
                },
                {
                    name: '130g xanh',
                    img: 'assets/images/p12.jpg',
                    price: 40000
                }
            ],
            2: [{
                    name: '88ml',
                    img: 'assets/images/p23.jpg',
                    price: 88000
                },
                {
                    name: '236ml',
                    img: 'assets/images/p24.jpg',
                    price: 284000
                }
            ],
            3: [{
                    name: 'Vàng',
                    img: 'assets/images/p34.jpg',
                    price: 295000
                },
                {
                    name: 'Đen',
                    img: 'assets/images/p35.jpg',
                    price: 295000
                },
                {
                    name: 'Trắng',
                    img: 'assets/images/p36.jpg',
                    price: 295000
                }
            ],
            4: [{
                    name: 'A01#',
                    img: 'assets/images/p41.jpg',
                    price: 36300
                },
                {
                    name: 'A02#',
                    img: 'assets/images/p42.jpg',
                    price: 36300
                }
            ],
            5: [{
                    name: '10ml',
                    img: 'assets/images/p5.jpg',
                    price: 311000
                },
                {
                    name: '30ml',
                    img: 'assets/images/p5.jpg',
                    price: 651000
                }
            ],
            6: [{
                    name: 'JUICY 20 + Glas 16',
                    img: 'assets/images/p61.jpg',
                    price: 269000
                },
                {
                    name: 'JUICY 23 + Glas 16',
                    img: 'assets/images/p62.jpg',
                    price: 269000
                }
            ],
            7: [{
                name: '60ml',
                img: 'assets/images/p7.jpg',
                price: 65600
            }],
            8: [{
                    name: 'Sạch da giảm nhờn',
                    img: 'assets/images/p81.jpg',
                    price: 254000
                },
                {
                    name: 'Dịu nhẹ da nhạy',
                    img: 'assets/images/p82.jpg',
                    price: 254000
                },
                {
                    name: 'Dành cho da sạm',
                    img: 'assets/images/p83.jpg',
                    price: 254000
                }
            ],
            9: [{
                    name: 'NTT + SRM',
                    img: 'assets/images/p91.jpg',
                    price: 165000
                },
                {
                    name: 'NTT + KD',
                    img: 'assets/images/p92.jpg',
                    price: 120000
                },
                {
                    name: 'SRM + KD',
                    img: 'assets/images/p93.jpg',
                    price: 139000
                }
            ],
            10: [{
                    name: 'Taupe',
                    img: 'assets/images/p101.jpg',
                    price: 264000
                },
                {
                    name: 'Best ever',
                    img: 'assets/images/p102.jpg',
                    price: 264000
                }
            ],

            //skincare

            12: [{
                    name: 'Hồng - Vàng - Tím',
                    img: 'assets/images/a24.jpg',
                    price: 266000
                },
                {
                    name: 'Hồng - Vàng - Xanh',
                    img: 'assets/images/a25.jpg',
                    price: 266000
                }
            ],
            11: [{
                    name: 'SET A',
                    img: 'assets/images/a14.jpg',
                    price: 453000
                },
                {
                    name: 'SET B',
                    img: 'assets/images/a15.jpg',
                    price: 453000
                }
                
            ],
            13: [{
                    name: 'Mặt nạ COLORKEY LUMINOUS BHA',
                    img: 'assets/images/a34.jpg',
                    price: 129000
                }
            ],

            //makeup

            14: [{
                    name: 'Xịt khóa đen',
                    img: 'assets/images/p144.jpg',
                    price: 141000
                },
                {
                    name: 'Xịt khóa xanh',
                    img: 'assets/images/p145.jpg',
                    price: 141000
                }
            ],
            15: [{
                    name: 'Mascara COLORKEY',
                    img: 'assets/images/p154.jpg',
                    price: 141000
                }
                
            ],
            16: [{
                    name: 'Kem lót màu tím',
                    img: 'assets/images/p164.jpg',
                    price: 149000
                },
                {
                    name: 'Kem lót màu xanh',
                    img: 'assets/images/p165.jpg',
                    price: 149000
                }
            ],
            17: [{
                    name: 'D-511-2',
                    img: 'assets/images/p174.jpg',
                    price: 58000
                },
                {
                    name: 'D-511-3',
                    img: 'assets/images/p175.jpg',
                    price: 58000
                }
            ],
            18: [{
                    name: 'C01-Bản thường',
                    img: 'assets/images/p184.jpg',
                    price: 260000
                },
                {
                    name: 'C02-bản thường',
                    img: 'assets/images/p185.jpg',
                    price: 260000
                }
            ],
            19: [{
                    name: '01#',
                    img: 'assets/images/p194.jpg',
                    price: 40000
                },
                {
                    name: '02#',
                    img: 'assets/images/p195.jpg',
                    price: 40000
                }
            ],
            20: [{
                    name: 'Pink',
                    img: 'assets/images/p202.jpg',
                    price: 250000
                },
                {
                    name: 'Black',
                    img: 'assets/images/p203.jpg',
                    price: 250000
                }
            ],
           

            //bodycare

            21: [{
                    name: 'ARBUTIN + RAU MÁ 250G',
                    img: 'assets/images/p514.jpg',
                    price: 485000
                },
                {
                    name: 'ARBUTIN + RAU MÁ 500G',
                    img: 'assets/images/p515.jpg',
                    price: 267300
                }
            ],
            22: [{
                    name: '30ml',
                    img: 'assets/images/p224.jpg',
                    price: 315000
                },
                {
                    name: '50ml',
                    img: 'assets/images/p225.jpg',
                    price: 500000
                }
            ],
            23: [{
                    name: 'Vỉ trắng arbutin',
                    img: 'assets/images/p234.jpg',
                    price: 45000
                },
                {
                    name: 'Dưỡng thể Snail Gold',
                    img: 'assets/images/p235.jpg',
                    price: 98000
                }
            ],
            24: [{
                    name: '250ml',
                    img: 'assets/images/p244.jpg',
                    price: 89000
                },
                {
                    name: '400ml',
                    img: 'assets/images/p245.jpg',
                    price: 990000
                }
            ],
            25: [{
                    name: '300ml',
                    img: 'assets/images/p254.jpg',
                    price: 79000
                },
                {
                    name: '500ml',
                    img: 'assets/images/p255.jpg',
                    price: 99000
                }
            ],
            26: [{
                    name: '300ml',
                    img: 'assets/images/p264.jpg',
                    price: 118000
                },
                {
                    name: '500ml',
                    img: 'assets/images/p265.jpg',
                    price: 210000
                }
            ],
            27: [{
                    name: '500ml',
                    img: 'assets/images/p274.jpg',
                    price: 977000
                },
                {
                    name: '750ml',
                    img: 'assets/images/p275.jpg',
                    price: 1000000
                }
            ],
            28: [{
                    name: '300ml',
                    img: 'assets/images/p284.jpg',
                    price: 355000
                },
                {
                    name: '600ml',
                    img: 'assets/images/p285.jpg',
                    price: 400000
                }
            ],
            29: [{
                    name: '450ml',
                    img: 'assets/images/p294.jpg',
                    price: 138000
                },
                {
                    name: '600ml',
                    img: 'assets/images/p295.jpg',
                    price: 150000
                }
            ],
            30: [{
                    name: 'Into the night',
                    img: 'assets/images/p304.jpg',
                    price: 259000
                },
                {
                    name: 'Gingham',
                    img: 'assets/images/p305.jpg',
                    price: 245000
                }
            ],

            // haircare
            31: [{
                    name: 'XBUOI_SVANG',
                    img: 'assets/images/p312.jpg',
                    price: 255150
                },
                {
                    name: 'XBUOI_SHONG',
                    img: 'assets/images/p313.jpg',
                    price: 267300
                }
            ],
            32: [{
                    name: 'Lẻ 1 chai',
                    img: 'assets/images/p322.jpg',
                    price: 314000
                },
                {
                    name: 'Combo 2 chai',
                    img: 'assets/images/p323.jpg',
                    price: 589000
                }
            ],

            33: [{
                    name: 'Tóc thường',
                    img: 'assets/images/p334.jpg',
                    price: 217000
                },
                {
                    name: 'Phục hồi tóc',
                    img: 'assets/images/p335.jpg',
                    price: 316000
                }
            ],

            34: [{
                    name: '300gr',
                    img: 'assets/images/p344.jpg',
                    price: 46000
                },
                {
                    name: '500gr',
                    img: 'assets/images/p344.jpg',
                    price: 54000
                }
            ],

            35: [{
                    name: 'Olaplex  No0 155ml',
                    img: 'assets/images/p354.jpg',
                    price: 599000
                },
                {
                    name: 'Olaplex  No4 155ml',
                    img: 'assets/images/p355.jpg',
                    price: 599000
                }
            ],

            36: [{
                    name: 'Serum bóng tóc',
                    img: 'assets/images/p364.jpg',
                    price: 250000
                },
                {
                    name: 'Tinh chất essence',
                    img: 'assets/images/p365.jpg',
                    price: 250000
                }
            ],

            37: [{
                    name: '120ml',
                    img: 'assets/images/p374.jpg',
                    price: 69000
                },
                {
                    name: '250ml',
                    img: 'assets/images/p375.jpg',
                    price: 69000
                }
            ],

            38: [{
                    name: 'DG Oil + ST Pore 400ml',
                    img: 'assets/images/p384.jpg',
                    price: 242000
                },
                {
                    name: 'DG Oil Control 400ml',
                    img: 'assets/images/p385.jpg',
                    price: 159000
                }
            ],

            39: [{
                    name: '150ml',
                    img: 'assets/images/p394.jpg',
                    price: 260000
                },
                {
                    name: '240ml',
                    img: 'assets/images/p395.jpg',
                    price: 300000
                }
            ],

            40: [{
                    name: '500gr',
                    img: 'assets/images/p404.jpg',
                    price: 383000
                },
                {
                    name: '700gr',
                    img: 'assets/images/p405.jpg',
                    price: 430000
                }
            ],
            // perfume
            41: [{
                    name: '10ml',
                    img: 'assets/images/p412.jpg',
                    price: 369000
                },
                {
                    name: '20ml',
                    img: 'assets/images/p413.jpg',
                    price: 507340
                }
            ],
            42: [{
                    name: '10ml',
                    img: 'assets/images/p427.jpg',
                    price: 264000
                },
                {
                    name: '30ml',
                    img: 'assets/images/p427.jpg',
                    price: 264000
                }
            ],

            43: [
                {
                    name: '30ml',
                    img: 'assets/images/p438.jpg',
                    price: 264000
                }
            ],
            44: [{
                    name: '10ml',
                    img: 'assets/images/p443.jpg',
                    price: 264000
                },
                {
                    name: '30ml',
                    img: 'assets/images/p445.jpg',
                    price: 264000
                }
            ],
            45: [{
                    name: '10ml',
                    img: 'assets/images/p454.jpg',
                    price: 264000
                },
                {
                    name: '30ml',
                    img: 'assets/images/p455.jpg',
                    price: 264000
                }
            ],
            46: [{
                    name: '10ml',
                    img: 'assets/images/p464.jpg',
                    price: 264000
                },
                {
                    name: '30ml',
                    img: 'assets/images/p465.jpg',
                    price: 264000
                }
            ],
            47: [{
                    name: 'Bouncy 50ml',
                    img: 'assets/images/p474.jpg',
                    price: 264000
                },
                {
                    name: 'Urban 50ml',
                    img: 'assets/images/p475.jpg',
                    price: 264000
                }
            ],
             48: [{
                    name: '10ml',
                    img: 'assets/images/p484.jpg',
                    price: 264000
                },
                {
                    name: '30ml',
                    img: 'assets/images/p484.jpg',
                    price: 264000
                }
            ],
            49: [{
                    name: 'LE MALE LE PARFUM',
                    img: 'assets/images/p493.jpg',
                    price: 264000
                },
                {
                    name: 'LE MALE ELIXIR',
                    img: 'assets/images/p494.jpg',
                    price: 264000
                }
            ],
            50: [{
                    name: '10ml',
                    img: 'assets/images/p504.jpg',
                    price: 264000
                },
                {
                    name: '30ml',
                    img: 'assets/images/p505.jpg',
                    price: 264000
                }
            ],
        };

        function loadVariants(productId) {
            const variantOptions = document.getElementById('variant-options-page');
            const variants = productVariants[productId] || [];
            variantOptions.innerHTML = '';

            if (variants.length > 0) {
                variants.forEach((variant, index) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = `variant-btn ${index === 0 ? 'active' : ''}`;
                    btn.textContent = variant.name;
                    btn.setAttribute('data-img', variant.img);
                    btn.setAttribute('data-price', variant.price);
                    btn.onclick = () => selectVariant(btn, productId);
                    variantOptions.appendChild(btn);
                });

                // Chọn mặc định và cập nhật form
                const defaultVariant = variants[0];
                selectVariant(variantOptions.querySelector('.variant-btn'), productId);
            } else {
                variantOptions.innerHTML = '<p>Không có phân loại</p>';
            }
        }

        // Hàm chọn phân loại và cập nhật form
        function selectVariant(btn, productId) {
            const variantOptions = document.getElementById('variant-options-page');
            variantOptions.querySelectorAll('.variant-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const img = btn.getAttribute('data-img');
            const price = parseInt(btn.getAttribute('data-price'));
            const option = btn.textContent.trim();

            // Cập nhật form "Mua ngay"
            document.getElementById('buyNowOption').value = option;
            document.getElementById('buyNowImg').value = img;
            document.getElementById('buyNowPrice').value = price;

            // Cập nhật form "Thêm vào giỏ"
            document.getElementById('cartOption').value = option;
            document.getElementById('cartImg').value = img;
            document.getElementById('cartPrice').value = price;
        }

        // Hàm điều chỉnh số lượng
        function changeQty(change) {
            const qtyInput = document.getElementById('product_qty');
            let val = parseInt(qtyInput.value) || 1;
            val += change;
            if (val < 1) val = 1;
            qtyInput.value = val;
            document.getElementById('buy_now_qty').value = val; // Cập nhật số lượng cho "Mua ngay"
        }

        // Tải phân loại khi trang được tải
        document.addEventListener('DOMContentLoaded', () => {
            loadVariants(<?= $product['id'] ?>);
        });

        function changeImage(imgElement) {
            document.getElementById('mainProductImage').src = imgElement.src;
            document.querySelectorAll('.thumbnail-list img').forEach(img => img.classList.remove('active'));
            imgElement.classList.add('active');
        }

        function openCartModal(name, price, img, id) {
            const modal = document.getElementById('cartModal');
            modal.style.display = 'flex';

            document.getElementById('modalTitle').textContent = name;
            document.getElementById('modalProductImg').src = 'assets/' + img;
            document.getElementById('modalProductName').value = name;
            document.getElementById('modalProductQty').value = 1;
            document.getElementById('quantity').value = 1;

            const optionGroup = document.getElementById('variant-options');
            optionGroup.innerHTML = '';

            const variants = productVariants[id] || [];
            if (variants.length > 0) {
                variants.forEach((variant, index) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = `option-btn ${index === 0 ? 'active' : ''}`;
                    btn.textContent = variant.name;
                    btn.setAttribute('data-img', variant.img);
                    btn.setAttribute('data-price', variant.price);
                    btn.onclick = () => selectOption(btn);
                    optionGroup.appendChild(btn);
                });

                const firstVariant = variants[0];
                document.getElementById('modalPrice').textContent = firstVariant.price.toLocaleString('vi-VN') + '₫';
                document.getElementById('modalProductPrice').value = firstVariant.price;
                document.getElementById('modalProductOption').value = firstVariant.name;
                updateBuyNowForm(firstVariant.name, firstVariant.img, firstVariant.price);
            } else {
                optionGroup.innerHTML = '<p>Không có phân loại</p>';
                document.getElementById('modalPrice').textContent = price.toLocaleString('vi-VN') + '₫';
                document.getElementById('modalProductPrice').value = price;
                document.getElementById('modalProductOption').value = '';
                updateBuyNowForm('', img, price);
            }
        }

        function closeCartModal() {
            document.getElementById('cartModal').style.display = 'none';
        }

        function selectOption(btn) {
            btn.parentNode.querySelectorAll('.option-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const img = btn.getAttribute('data-img');
            const price = parseInt(btn.getAttribute('data-price'));
            const option = btn.textContent.trim();
            document.getElementById('modalProductImg').src = img;
            document.getElementById('modalPrice').textContent = price.toLocaleString('vi-VN') + '₫';
            document.getElementById('modalProductPrice').value = price;
            document.getElementById('modalProductOption').value = option;
            updateBuyNowForm(option, img, price);
        }

        function decreaseQty() {
            const qtyInput = document.getElementById('quantity');
            let val = parseInt(qtyInput.value);
            if (val > 1) val--;
            qtyInput.value = val;
            document.getElementById('modalProductQty').value = val;
            document.getElementById('buy_now_qty').value = val;
        }

        function increaseQty() {
            const qtyInput = document.getElementById('quantity');
            let val = parseInt(qtyInput.value);
            val++;
            qtyInput.value = val;
            document.getElementById('modalProductQty').value = val;
            document.getElementById('buy_now_qty').value = val;
        }

        function changeQty(change) {
            const qtyInput = document.getElementById('product_qty');
            let val = parseInt(qtyInput.value) || 1;
            val += change;
            if (val < 1) val = 1;
            qtyInput.value = val;
            document.getElementById('buy_now_qty').value = val;
        }

        function updateBuyNowForm(option, img, price) {
            document.getElementById('buyNowOption').value = option;
            document.getElementById('buyNowImg').value = img;
            document.getElementById('buyNowPrice').value = price;
            document.getElementById('buy_now_qty').value = document.getElementById('quantity').value;
        }

        document.addEventListener('DOMContentLoaded', () => {
            const thumbnailList = document.getElementById('thumbnailList');
            const variants = productVariants[<?= $product['id'] ?>] || [];
            const defaultImage = '<?= htmlspecialchars($product['product_image']) ?>';

            if (variants.length > 0) {
                variants.forEach((variant, index) => {
                    const img = document.createElement('img');
                    img.src = variant.img;
                    img.alt = variant.name;
                    img.className = index === 0 ? 'active' : '';
                    img.onclick = () => changeImage(img);
                    thumbnailList.appendChild(img);
                });
            } else {
                const img = document.createElement('img');
                img.src = defaultImage;
                img.alt = '<?= htmlspecialchars($product['name']) ?>';
                img.className = 'active';
                img.onclick = () => changeImage(img);
                thumbnailList.appendChild(img);
            }

            const stars = document.querySelectorAll('.star-rating .star');
            const ratingInput = document.getElementById('rating');
            stars.forEach(star => {
                star.addEventListener('click', () => {
                    const value = parseInt(star.getAttribute('data-value'));
                    ratingInput.value = value;
                    stars.forEach(s => {
                        const sValue = parseInt(s.getAttribute('data-value'));
                        s.classList.toggle('filled', sValue <= value);
                    });
                });
                star.addEventListener('mouseover', () => {
                    const value = parseInt(star.getAttribute('data-value'));
                    stars.forEach(s => {
                        const sValue = parseInt(s.getAttribute('data-value'));
                        s.classList.toggle('filled', sValue <= value);
                    });
                });
                star.addEventListener('mouseout', () => {
                    const value = parseInt(ratingInput.value);
                    stars.forEach(s => {
                        const sValue = parseInt(s.getAttribute('data-value'));
                        s.classList.toggle('filled', sValue <= value);
                    });
                });
            });
        });
    </script>
</body>

</html>
<?php
mysqli_close($conn);
?>