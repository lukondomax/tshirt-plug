<?php

session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include "database.php";


// ======================================================
// ADD RECEIVED CLOTHES
// ======================================================

if (isset($_POST['add_stock'])) {

    $product_name = trim($_POST['product_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $size = trim($_POST['size'] ?? '');
    $color = trim($_POST['color'] ?? '');

    $quantity = (int)($_POST['quantity'] ?? 0);
    $buying_price = (float)($_POST['buying_price'] ?? 0);


    // ==================================================
    // VALIDATION
    // ==================================================

    if ($product_name === '' || $quantity <= 0) {

        $_SESSION['error'] =
            "Product name and valid quantity are required.";

        header("Location: reports.php");
        exit();
    }


    if ($buying_price < 0) {

        $_SESSION['error'] =
            "Buying price cannot be negative.";

        header("Location: reports.php");
        exit();
    }


    // ==================================================
    // DATE RECEIVED
    // ==================================================

    $date_received = date("Y-m-d");


    // ==================================================
    // INSERT INTO RECEIVED INVENTORY
    // ==================================================

    $stmt = mysqli_prepare(
        $conn,

        "INSERT INTO products
        (
            product_name,
            category,
            brand,
            size,
            color,
            quantity,
            quantity_taken,
            buying_price,
            date_received,
            sale_date
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            0,
            ?,
            ?,
            NULL
        )"
    );


    if (!$stmt) {

        $_SESSION['error'] =
            "Could not prepare inventory record: "
            . mysqli_error($conn);

        header("Location: reports.php");
        exit();
    }


    // ==================================================
    // CORRECT BINDING
    //
    // product_name   = s
    // category       = s
    // brand          = s
    // size           = s
    // color          = s
    // quantity       = i
    // buying_price   = d
    // date_received  = s
    //
    // TOTAL = 8 VARIABLES
    // TOTAL = 8 TYPE CHARACTERS
    //
    // sssssids
    // ==================================================

    mysqli_stmt_bind_param(
        $stmt,
        "sssssids",
        $product_name,
        $category,
        $brand,
        $size,
        $color,
        $quantity,
        $buying_price,
        $date_received
    );


    // ==================================================
    // EXECUTE
    // ==================================================

    if (mysqli_stmt_execute($stmt)) {

        $_SESSION['success'] =
            "Received clothes saved successfully.";

    } else {

        $_SESSION['error'] =
            "Received clothes could not be saved: "
            . mysqli_stmt_error($stmt);
    }


    mysqli_stmt_close($stmt);


    // ==================================================
    // RETURN TO REPORTS PAGE
    // ==================================================

    header("Location: reports.php");
    exit();
}


// ======================================================
// TAKE CLOTHES
// ======================================================

if (isset($_POST['take'])) {

    $id = (int)($_POST['id'] ?? 0);

    $person = trim(
        $_POST['person_name'] ?? ''
    );

    $amount = (int)(
        $_POST['quantity_taken'] ?? 0
    );


    // ==================================================
    // VALIDATION
    // ==================================================

    if (
        $id <= 0 ||
        $amount <= 0 ||
        $person === ''
    ) {

        $_SESSION['error'] =
            "Please enter a valid person name and quantity.";

        header("Location: reports.php");
        exit();
    }


    // ==================================================
    // GET CURRENT INVENTORY
    // ==================================================

    $stmt = mysqli_prepare(
        $conn,

        "SELECT
            quantity,
            quantity_taken
         FROM products
         WHERE id = ?
         AND date_received IS NOT NULL
         AND sale_date IS NULL"
    );


    if (!$stmt) {

        $_SESSION['error'] =
            "Could not check inventory.";

        header("Location: reports.php");
        exit();
    }


    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id
    );


    mysqli_stmt_execute($stmt);


    $result =
        mysqli_stmt_get_result($stmt);


    $row =
        mysqli_fetch_assoc($result);


    mysqli_stmt_close($stmt);


    if (!$row) {

        $_SESSION['error'] =
            "Inventory record not found.";

        header("Location: reports.php");
        exit();
    }


    $current_quantity =
        (int)$row['quantity'];


    $current_taken =
        (int)($row['quantity_taken'] ?? 0);


    // ==================================================
    // CHECK AVAILABLE STOCK
    // ==================================================

    if ($amount > $current_quantity) {

        $_SESSION['error'] =
            "Not enough stock available. Available stock: "
            . $current_quantity;

        header("Location: reports.php");
        exit();
    }


    $new_quantity =
        $current_quantity - $amount;


    $new_taken =
        $current_taken + $amount;


    // ==================================================
    // UPDATE INVENTORY
    // ==================================================

    $stmt = mysqli_prepare(
        $conn,

        "UPDATE products
         SET
            quantity = ?,
            quantity_taken = ?,
            person_name = ?
         WHERE id = ?
         AND date_received IS NOT NULL
         AND sale_date IS NULL"
    );


    if (!$stmt) {

        $_SESSION['error'] =
            "Could not update inventory.";

        header("Location: reports.php");
        exit();
    }


    mysqli_stmt_bind_param(
        $stmt,
        "iisi",
        $new_quantity,
        $new_taken,
        $person,
        $id
    );


    if (mysqli_stmt_execute($stmt)) {

        $_SESSION['success'] =
            "Clothes taken successfully.";

    } else {

        $_SESSION['error'] =
            "Clothes could not be taken: "
            . mysqli_stmt_error($stmt);
    }


    mysqli_stmt_close($stmt);


    header("Location: reports.php");
    exit();
}


// ======================================================
// SEARCH RECEIVED CLOTHES
// ======================================================

$search =
    trim($_GET['search'] ?? '');

$page_size = 10;

if ($search !== '') {

    $like =
        "%" . $search . "%";

    $count_stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*)
         FROM products
         WHERE
            date_received IS NOT NULL
            AND sale_date IS NULL
            AND
            (
                product_name LIKE ?
                OR category LIKE ?
                OR brand LIKE ?
                OR size LIKE ?
                OR color LIKE ?
                OR person_name LIKE ?
                OR date_received LIKE ?
            )"
    );


    if (!$count_stmt) {

        die(
            "Search count query failed: "
            . mysqli_error($conn)
        );
    }


    mysqli_stmt_bind_param(
        $count_stmt,
        "sssssss",
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like
    );

    if (!mysqli_stmt_execute($count_stmt)) {

        die(
            "Search count query failed: "
            . mysqli_stmt_error($count_stmt)
        );

    }

    mysqli_stmt_bind_result($count_stmt, $total_records);
    mysqli_stmt_fetch($count_stmt);
    $total_records = (int)$total_records;
    mysqli_stmt_close($count_stmt);

} else {

    $count_result = mysqli_query(
        $conn,
        "SELECT COUNT(*)
         FROM products
         WHERE
            date_received IS NOT NULL
            AND sale_date IS NULL"
    );

    if (!$count_result) {
        die("Could not count received inventory: " . mysqli_error($conn));
    }

    $count_row = mysqli_fetch_row($count_result);
    $total_records = (int)$count_row[0];

}

$total_pages = max(1, (int)ceil($total_records / $page_size));
$page_input = isset($_GET['page']) && is_string($_GET['page'])
    ? (int)$_GET['page']
    : 1;
$page = min(max(1, $page_input), $total_pages);
$offset = ($page - 1) * $page_size;

if ($search !== '') {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            id,
            product_name,
            category,
            brand,
            size,
            color,
            quantity,
            quantity_taken,
            buying_price,
            person_name,
            date_received
         FROM products
         WHERE
            date_received IS NOT NULL
            AND sale_date IS NULL
            AND
            (
                product_name LIKE ?
                OR category LIKE ?
                OR brand LIKE ?
                OR size LIKE ?
                OR color LIKE ?
                OR person_name LIKE ?
                OR date_received LIKE ?
            )
         ORDER BY
            date_received DESC,
            id DESC
         LIMIT ? OFFSET ?"
    );

    if (!$stmt) {
        die("Search query failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $stmt,
        "sssssssii",
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $page_size,
        $offset
    );

    if (!mysqli_stmt_execute($stmt)) {
        die("Search query failed: " . mysqli_stmt_error($stmt));
    }

    $records = mysqli_stmt_get_result($stmt);

    if (!$records) {
        die("Could not load matching inventory: " . mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);

} else {

    $records = mysqli_query(
        $conn,
        "SELECT
            id,
            product_name,
            category,
            brand,
            size,
            color,
            quantity,
            quantity_taken,
            buying_price,
            person_name,
            date_received
         FROM products
         WHERE
            date_received IS NOT NULL
            AND sale_date IS NULL
         ORDER BY
            date_received DESC,
            id DESC
         LIMIT $page_size OFFSET $offset"
    );

}

if (!$records) {
    die("Could not load received inventory: " . mysqli_error($conn));
}

$previous_page_url = 'reports.php?' . http_build_query([
    'search' => $search,
    'page' => max(1, $page - 1)
]);
$next_page_url = 'reports.php?' . http_build_query([
    'search' => $search,
    'page' => min($total_pages, $page + 1)
]);

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
T-Shirt Plug - Received Clothes
</title>


<style>

/* =====================================================
   GENERAL
===================================================== */

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f7fb;

    color: #222;
}


/* =====================================================
   SIDEBAR
===================================================== */

.sidebar {

    position: fixed;

    left: 0;
    top: 0;

    width: 240px;

    height: 100vh;

    background: #0d47a1;

    color: white;

    overflow-y: auto;
}


.sidebar h2 {

    text-align: center;

    margin: 0;

    padding: 20px;

    border-bottom:
        1px solid
        rgba(255,255,255,0.15);
}


.sidebar a {

    display: block;

    padding: 15px 20px;

    color: white;

    text-decoration: none;

    transition: 0.2s;
}


.sidebar a:hover {

    background: #1565c0;
}


.sidebar a.active {

    background: #1565c0;

    font-weight: bold;

    border-left:
        5px solid white;
}


/* =====================================================
   MAIN
===================================================== */

.main {

    margin-left: 240px;

    padding: 30px;
}


.box {

    background: white;

    padding: 25px;

    margin-bottom: 25px;

    border-radius: 15px;

    box-shadow:
        0 5px 15px
        rgba(0,0,0,0.08);
}


.box h2 {

    margin-top: 0;

    color: #222;
}


/* =====================================================
   MESSAGES
===================================================== */

.success {

    background: #d4edda;

    color: #155724;

    padding: 14px;

    margin-bottom: 20px;

    border-radius: 7px;

    border-left:
        5px solid #198754;
}


.error {

    background: #f8d7da;

    color: #721c24;

    padding: 14px;

    margin-bottom: 20px;

    border-radius: 7px;

    border-left:
        5px solid #dc3545;
}


/* =====================================================
   ADD RECEIVED CLOTHES FORM
===================================================== */

.received-form {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 12px;
}


.received-form input {

    width: 100%;

    padding: 12px;

    border:
        1px solid #ccc;

    border-radius: 7px;

    font-size: 14px;

    outline: none;
}


.received-form input:focus {

    border-color: #0d47a1;

    box-shadow:
        0 0 0 2px
        rgba(13,71,161,0.1);
}


.received-form button {

    width: 100%;

    padding: 12px;

    background: #0d47a1;

    color: white;

    border: none;

    border-radius: 7px;

    cursor: pointer;

    font-size: 14px;

    font-weight: bold;
}


.received-form button:hover {

    background: #083579;
}


/* =====================================================
   SEARCH
===================================================== */

.search-form {

    display: flex;

    gap: 8px;

    margin-bottom: 20px;
}


.search-form input {

    flex: 1;

    padding: 12px;

    border:
        1px solid #ccc;

    border-radius: 7px;

    outline: none;
}


.search-form button {

    padding:
        12px 20px;

    background: #0d47a1;

    color: white;

    border: none;

    border-radius: 7px;

    cursor: pointer;
}


.search-form button:hover {

    background: #083579;
}


/* =====================================================
   TABLE
===================================================== */

.table-container {

    width: 100%;

    overflow-x: auto;
}


table {

    width: 100%;

    min-width: 1200px;

    border-collapse: collapse;
}


th {

    background: #0d47a1;

    color: white;

    padding: 12px;

    border: 1px solid #ddd;

    white-space: nowrap;
}


td {

    padding: 10px;

    border: 1px solid #ddd;

    text-align: center;

    white-space: nowrap;
}


tbody tr:nth-child(even) {

    background: #f8f9fa;
}


tbody tr:hover {

    background: #eef4ff;
}

.pagination {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 12px;

    margin-top: 16px;
}


.pagination a,
.pagination span {

    display: inline-block;

    padding: 8px 12px;

    border-radius: 5px;
}


.pagination a {

    background: #0d47a1;

    color: white;

    text-decoration: none;
}


.pagination a:hover {

    background: #1565c0;
}


.pagination .disabled {

    background: #e9ecef;

    color: #777;
}


/* =====================================================
   TAKE FORM
===================================================== */

.take-form {

    display: flex;

    flex-direction: column;

    gap: 6px;

    align-items: center;
}


.take-form input {

    width: 130px;

    padding: 8px;

    border:
        1px solid #ccc;

    border-radius: 5px;
}


.take-button {

    background: #198754;

    color: white;

    border: none;

    padding:
        9px 20px;

    border-radius: 5px;

    cursor: pointer;

    font-weight: bold;
}


.take-button:hover {

    background: #146c43;
}


/* =====================================================
   EDIT / DELETE
===================================================== */

.edit {

    color: #1565c0;

    text-decoration: none;

    font-weight: bold;

    margin-right: 10px;
}


.edit:hover {

    text-decoration: underline;
}


.delete {

    color: #d32f2f;

    text-decoration: none;

    font-weight: bold;
}


.delete:hover {

    text-decoration: underline;
}


/* =====================================================
   OUT OF STOCK
===================================================== */

.out-stock {

    color: #d32f2f;

    font-weight: bold;
}


/* =====================================================
   MOBILE
===================================================== */

@media (max-width: 1000px) {

    .received-form {

        grid-template-columns:
            repeat(2, 1fr);
    }
}


@media (max-width: 700px) {

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;
    }


    .sidebar a {

        display: inline-block;

        padding: 12px;
    }


    .sidebar h2 {

        padding: 15px;
    }


    .main {

        margin-left: 0;

        padding: 15px;
    }


    .received-form {

        grid-template-columns: 1fr;
    }


    .search-form {

        flex-direction: column;
    }


    .search-form input {

        width: 100%;
    }
}

</style>

<link rel="stylesheet" href="sidebar.css">

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<?php include "sidebar.php"; ?>

<div class="main">


    <!-- ================================================
         SUCCESS MESSAGE
    ================================================= -->

    <?php if (isset($_SESSION['success'])): ?>

        <div class="success">

            <?= htmlspecialchars(
                $_SESSION['success']
            ) ?>

        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>


    <!-- ================================================
         ERROR MESSAGE
    ================================================= -->

    <?php if (isset($_SESSION['error'])): ?>

        <div class="error">

            <?= htmlspecialchars(
                $_SESSION['error']
            ) ?>

        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>


    <!-- ================================================
         ADD RECEIVED CLOTHES
    ================================================= -->

    <div class="box">

        <h2>
            Add Received Clothes
        </h2>


        <form
            method="POST"
            action="reports.php"
            class="received-form"
        >


            <input
                type="text"
                name="product_name"
                placeholder="Product Name"
                required
            >


            <input
                type="text"
                name="category"
                placeholder="Category"
            >


            <input
                type="text"
                name="brand"
                placeholder="Brand"
            >


            <input
                type="text"
                name="size"
                placeholder="Size"
            >


            <input
                type="text"
                name="color"
                placeholder="Color"
            >


            <input
                type="number"
                name="quantity"
                placeholder="Quantity Received"
                min="1"
                required
            >


            <input
                type="number"
                name="buying_price"
                placeholder="Buying Price"
                min="0"
                step="0.01"
            >


            <button
                type="submit"
                name="add_stock"
            >
                Save Received Clothes
            </button>


        </form>

    </div>


    <!-- ================================================
         RECEIVED CLOTHES INVENTORY
    ================================================= -->

    <div class="box">

        <h2>
            Received Clothes Inventory
        </h2>


        <!-- SEARCH -->

        <form
            method="GET"
            action="reports.php"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Search product, category, brand, person..."
            >


            <button type="submit">
                Search
            </button>

        </form>


        <!-- TABLE -->

        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>Product</th>

                        <th>Category</th>

                        <th>Brand</th>


                        <th>Available</th>

                        <th>Taken</th>

                        <th>Buying Price</th>

                        <th>Person</th>

                        <th>Date Received</th>

                        <th>Take Clothes</th>

                        <th>Action</th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    $records &&
                    mysqli_num_rows($records) > 0
                ): ?>


                    <?php while (
                        $row = mysqli_fetch_assoc($records)
                    ): ?>


                        <tr>


                            <td>
                                <?= htmlspecialchars(
                                    $row['product_name']
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $row['category']
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $row['brand']
                                ) ?>
                            </td>



                            <td>

                                <strong>

                                    <?= number_format(
                                        (int)$row['quantity']
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <?= number_format(
                                    (int)(
                                        $row['quantity_taken']
                                        ?? 0
                                    )
                                ) ?>

                            </td>


                            <td>

                                TSh
                                <?= number_format(
                                    (float)$row['buying_price'],
                                    2
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $row['person_name']
                                    ?? ''
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $row['date_received']
                                ) ?>

                            </td>


                            <!-- =================================
                                 TAKE CLOTHES
                            ================================== -->

                            <td>

                                <?php if (
                                    (int)$row['quantity'] > 0
                                ): ?>


                                    <form
                                        method="POST"
                                        action="reports.php"
                                        class="take-form"
                                    >


                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$row['id'] ?>"
                                        >


                                        <input
                                            type="text"
                                            name="person_name"
                                            placeholder="Person name"
                                            required
                                        >


                                        <input
                                            type="number"
                                            name="quantity_taken"
                                            placeholder="Quantity"
                                            min="1"
                                            max="<?= (int)$row['quantity'] ?>"
                                            required
                                        >


                                        <button
                                            type="submit"
                                            name="take"
                                            class="take-button"
                                        >
                                            Take
                                        </button>


                                    </form>


                                <?php else: ?>


                                    <span class="out-stock">
                                        Out of stock
                                    </span>


                                <?php endif; ?>

                            </td>


                            <!-- =================================
                                 EDIT / DELETE
                            ================================== -->

                            <td>


                                <a
                                    href="edit_inventory.php?id=<?= (int)$row['id'] ?>"
                                    class="edit"
                                >
                                    Edit
                                </a>


                                <a
                                    href="delete_inventory.php?id=<?= (int)$row['id'] ?>"
                                    class="delete"
                                    onclick="return confirm('Are you sure you want to delete this inventory record?');"
                                >
                                    Delete
                                </a>


                            </td>


                        </tr>


                    <?php endwhile; ?>


                <?php else: ?>


                    <tr>

                        <td colspan="12">

                            No received inventory records found.

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

        <?php if ($total_records > 0): ?>

            <nav class="pagination" aria-label="Received inventory pages">

                <span>
                    Showing <?= $offset + 1 ?>–<?= min($offset + $page_size, $total_records) ?>
                    of <?= $total_records ?> records
                </span>

                <div>
                    <?php if ($page > 1): ?>
                        <a href="<?= htmlspecialchars($previous_page_url) ?>">Previous</a>
                    <?php else: ?>
                        <span class="disabled">Previous</span>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="<?= htmlspecialchars($next_page_url) ?>">Next</a>
                    <?php else: ?>
                        <span class="disabled">Next</span>
                    <?php endif; ?>
                </div>

            </nav>

        <?php endif; ?>

    </div>


</div>

</body>

</html>
