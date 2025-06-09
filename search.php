<?php
// Kết nối CSDL
$conn = new mysqli("localhost", "root", "", "db_mypham");
$conn->set_charset("utf8");

// Kiểm tra lỗi kết nối
if ($conn->connect_error) {
    die("Kết nối CSDL thất bại: " . $conn->connect_error);
}

// Lấy từ khóa tìm kiếm nếu có
$query = isset($_GET['query']) ? trim(urldecode($_GET['query'])) : '';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả tìm kiếm - Luna Beauty</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<style>
    /* Existing styles remain unchanged */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #fff5f7 0%, #f8e9ec 100%);
        color: #333;
        min-height: 100vh;
        padding: 40px 20px;
    }

    /* Result Container */
    .result {
        background: #fff;
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px;
        border-radius: 24px;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .result:hover {
        transform: translateY(-8px);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.12);
    }

    h2 {
        text-align: center;
        color: #ff6b81;
        font-size: 32px;
        font-weight: 600;
        margin-bottom: 32px;
    }

    h2 em {
        font-style: italic;
        color: #555;
    }

    /* Product Grid */
    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 24px;
    }

    .product {
        background: #fff;
        border: 2px solid #ffe4e1;
        border-radius: 16px;
        padding: 20px;
        display: flex;
        flex-direction: column;
        align-items: center;
        transition: all 0.3s ease;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
    }

    .product img {
        height: 200px;
        object-fit: cover;
        border-radius: 4px;
        margin-bottom: 16px;
        transition: transform 0.3s ease;
    }

    .product-name {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.4em;
        max-height: 2.8em;
        /* 1.4em * 2 dòng */
    }




    .product-info {
        color: #555;
        font-size: 15px;
        margin-bottom: 16px;
        text-align: center;
    }

    .product-info .price {
        color: #333;
        font-weight: 600;
    }

    /* Buttons */
    .product button {
        width: 100%;
        padding: 12px;
        border: none;
        border-radius: 50px;
        font-size: 15px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .product button.cart-btn {
        background: linear-gradient(90deg, #ff6b81 0%, #ff8e53 100%);
        color: #fff;
        margin-bottom: 12px;
    }

    .product button.cart-btn:hover {
        background: linear-gradient(90deg, #ff4d68 0%, #ff7036 100%);
        box-shadow: 0 8px 24px rgba(255, 107, 129, 0.5);
        transform: translateY(-3px);
    }

    .product button.buy-btn {
        background: #fff;
        color: #ff6b81;
        border: 2px solid #ff6b81;
    }

    .product button.buy-btn:hover {
        background: #ff6b81;
        color: #fff;
        box-shadow: 0 6px 16px rgba(255, 107, 129, 0.4);
    }

    /* No Results */
    .no-results {
        text-align: center;
        color: #555;
        font-size: 18px;
        padding: 20px;
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }

    .modal-content {
        background: #fff;
        border-radius: 16px;
        padding: 24px;
        max-width: 500px;
        width: 90%;
        position: relative;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
    }

    .modal-content h3 {
        color: #ff6b81;
        font-size: 24px;
        margin-bottom: 16px;
    }

    .modal-content img {
        width: 100%;
        height: 200px;
        object-fit: cover;
        border-radius: 12px;
        margin-bottom: 16px;
    }

    .modal-content .variant-options {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }

    .modal-content .option-btn {
        padding: 8px 16px;
        border: 2px solid #ff6b81;
        background: #fff;
        color: #ff6b81;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .modal-content .option-btn.active {
        background: #ff6b81;
        color: #fff;
    }

    .modal-content .option-btn:hover {
        background: #ff6b81;
        color: #fff;
    }

    .modal-content .quantity-control {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 16px;
    }

    .modal-content .quantity-control button {
        padding: 8px 12px;
        border: 2px solid #ff6b81;
        background: #fff;
        color: #ff6b81;
        border-radius: 50px;
        cursor: pointer;
    }

    .modal-content .quantity-control input {
        width: 50px;
        text-align: center;
        border: 2px solid #ff6b81;
        border-radius: 8px;
        padding: 8px;
    }

    .modal-content .modal-actions {
        display: flex;
        gap: 16px;
    }

    .modal-content .modal-actions button {
        flex: 1;
        padding: 12px;
        border-radius: 50px;
        font-size: 15px;
        cursor: pointer;
    }

    .modal-content .modal-actions .add-to-cart {
        background: linear-gradient(90deg, #ff6b81 0%, #ff8e53 100%);
        color: #fff;
        border: none;
    }

    .modal-content .modal-actions .add-to-cart:hover {
        background: linear-gradient(90deg, #ff4d68 0%, #ff7036 100%);
    }

    .modal-content .modal-actions .close-btn {
        background: #fff;
        color: #ff6b81;
        border: 2px solid #ff6b81;
    }

    .modal-content .modal-actions .close-btn:hover {
        background: #ff6b81;
        color: #fff;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .result {
            max-width: 100%;
            padding: 32px;
        }

        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }

        .product img {
            height: 180px;
        }
    }

    @media (max-width: 768px) {
        body {
            padding: 24px 16px;
        }

        .result {
            padding: 24px;
        }

        h2 {
            font-size: 28px;
        }

        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }

        .product {
            padding: 16px;
        }

        .product img {
            height: 160px;
        }

        .product-name {
            font-size: 16px;
            min-height: 44px;
        }

        .product-info {
            font-size: 14px;
        }

        .product button {
            padding: 10px;
            font-size: 14px;
        }
    }

    @media (max-width: 600px) {
        body {
            padding: 16px;
        }

        .result {
            padding: 16px;
        }

        h2 {
            font-size: 24px;
        }

        .product-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .product {
            padding: 12px;
        }

        .product img {
            height: 140px;
        }

        .product-name {
            font-size: 15px;
            min-height: 40px;
        }

        .product-info {
            font-size: 13px;
        }

        .product button {
            padding: 8px;
            font-size: 13px;
        }
    }
</style>

<body>
    <div class="result">
        <?php if (!empty($query)): ?>
            <h2>Kết quả tìm kiếm cho: <em><?= htmlspecialchars($query) ?></em></h2>
            <?php
            // Truy vấn tìm kiếm không phân biệt chữ hoa/thường
            $sql = "SELECT * FROM product WHERE LOWER(name) LIKE ? OR LOWER(category) LIKE ?";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $param = '%' . mb_strtolower($query, 'UTF-8') . '%';
                $stmt->bind_param("ss", $param, $param);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    echo '<div class="product-grid">';
                    while ($row = $result->fetch_assoc()) {
                        echo '<div class="product">';
                        echo '<img src="assets/' . htmlspecialchars($row['product_image']) . '" alt="' . htmlspecialchars($row['name']) . '">';
                        echo '<div class="product-name">' . htmlspecialchars($row['name']) . '</div>';
                        echo '<div class="product-info">';
                        echo htmlspecialchars($row['category']) . ' - <span class="price">' . number_format($row['price']) . ' VNĐ</span>';
                        echo '</div>';
                        echo '<form method="POST" action="add_to_cart.php">';
                        echo '<input type="hidden" name="product_id" value="' . $row['id'] . '">';
                        echo '<input type="hidden" name="product_name" value="' . htmlspecialchars($row['name']) . '">';
                        echo '<input type="hidden" name="product_option" value="' . htmlspecialchars($row['category']) . '">';
                        echo '<input type="hidden" name="product_price" value="' . $row['price'] . '">';
                        echo '<input type="hidden" name="product_qty" value="1">';
                        echo '<button type="button" class="cart-btn" onclick="openCartModal(\'' . htmlspecialchars($row['name']) . '\', ' . $row['price'] . ', \'' . htmlspecialchars($row['product_image']) . '\', ' . $row['id'] . ')">';
                        echo '<i class="fas fa-cart-plus"></i> Thêm vào giỏ</button>';
                        echo '</form>';
                        echo '<form method="POST" action="checkout.php">';
                        echo '<input type="hidden" name="product_id" value="' . $row['id'] . '">';
                        echo '<input type="hidden" name="buy_now" value="1">';
                        echo '<button type="submit" class="buy-btn"><i class="fas fa-credit-card"></i> Mua ngay</button>';
                        echo '</form>';
                        echo '</div>';
                    }
                    echo '</div>';
                } else {
                    echo '<p class="no-results">Không tìm thấy sản phẩm nào phù hợp.</p>';
                }
                $stmt->close();
            } else {
                echo '<p class="no-results">Lỗi khi chuẩn bị truy vấn: ' . htmlspecialchars($conn->error) . '</p>';
            }
            ?>
        <?php else: ?>
            <p class="no-results">Vui lòng nhập từ khóa tìm kiếm.</p>
        <?php endif; ?>

        <?php $conn->close(); ?>
    </div>

    <!-- Cart Modal -->
    <div class="modal" id="cartModal">
        <div class="modal-content">
            <h3 id="modalTitle"></h3>
            <img id="modalProductImg" src="" alt="Product Image">
            <div class="variant-options" id="variant-options"></div>
            <div class="quantity-control">
                <button type="button" onclick="decreaseQty()">-</button>
                <input type="number" id="quantity" value="1" min="1">
                <button type="button" onclick="increaseQty()">+</button>
            </div>
            <p>Giá: <span id="modalPrice"></span></p>
            <form method="POST" action="add_to_cart.php">
                <input type="hidden" id="modalProductName" name="product_name">
                <input type="hidden" id="modalProductPrice" name="product_price">
                <input type="hidden" id="modalProductOption" name="product_option">
                <input type="hidden" id="modalProductQty" name="product_qty" value="1">
                <input type="hidden" id="modalProductId" name="product_id">
                <div class="modal-actions">
                    <button type="submit" class="add-to-cart">Thêm vào giỏ</button>
                    <button type="button" class="close-btn" onclick="closeCartModal()">Đóng</button>
                </div>
            </form>
        </div>
    </div>
</body>
<script>
    const productVariants = {
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
                img: 'assets/images/product2.jpg',
                price: 88000
            },
            {
                name: '236ml',
                img: 'assets/images/product2.jpg',
                price: 284000
            }
        ],
        3: [{
                name: 'Vàng',
                img: 'assets/images/p31.jpg',
                price: 295000
            },
            {
                name: 'Đen',
                img: 'assets/images/p32.jpg',
                price: 295000
            },
            {
                name: 'Trắng',
                img: 'assets/images/p33.jpg',
                price: 295000
            }
        ],
        4: [{
                name: 'A01#',
                img: 'assets/images/p11.jpg',
                price: 36300
            },
            {
                name: 'A01#',
                img: 'assets/images/p12.jpg',
                price: 36300
            },
            {
                name: 'A01#',
                img: 'assets/images/p13.jpg',
                price: 36300
            },
            {
                name: 'A01#',
                img: 'assets/images/p15.jpg',
                price: 36300
            },
            {
                name: 'A02#',
                img: 'assets/images/p14.jpg',
                price: 39600
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
                img: 'assets/images/p9.jpg',
                price: 165000
            },
            {
                name: 'NTT + KD',
                img: 'assets/images/p9.jpg',
                price: 120000
            },
            {
                name: 'SRM + KD',
                img: 'assets/images/p9.jpg',
                price: 139000
            },
            {
                name: 'NTT + TONER + SRM + KD',
                img: 'assets/images/p9.jpg',
                price: 304000
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
        ]
    };

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
        document.getElementById('modalProductId').value = id;

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
        } else {
            optionGroup.innerHTML = '<p>Không có phân loại</p>';
            document.getElementById('modalPrice').textContent = price.toLocaleString('vi-VN') + '₫';
            document.getElementById('modalProductPrice').value = price;
            document.getElementById('modalProductOption').value = '';
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
        document.getElementById('modalProductImg').src = img;
        document.getElementById('modalPrice').textContent = price.toLocaleString('vi-VN') + '₫';
        document.getElementById('modalProductPrice').value = price;
        document.getElementById('modalProductOption').value = btn.textContent.trim();
    }

    function decreaseQty() {
        const qtyInput = document.getElementById('quantity');
        let val = parseInt(qtyInput.value);
        if (val > 1) val--;
        qtyInput.value = val;
        document.getElementById('modalProductQty').value = val;
    }

    function increaseQty() {
        const qtyInput = document.getElementById('quantity');
        let val = parseInt(qtyInput.value);
        val++;
        qtyInput.value = val;
        document.getElementById('modalProductQty').value = val;
    }

    function changeQty(change) {
        const qtyInput = document.getElementById('product_qty');
        let val = parseInt(qtyInput.value) || 1;
        val += change;
        if (val < 1) val = 1;
        qtyInput.value = val;
    }
</script>

</html>