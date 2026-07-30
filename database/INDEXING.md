# Database indexing review

The source archive did not include an authoritative database migration history. Check the existing indexes with `SHOW INDEX FROM table_name` before adding any of the suggestions below, and use `EXPLAIN` on slow queries to confirm that an index is useful.

Columns used frequently for lookup, filtering, joins or ordering should normally be indexed:

- `User.email`, `User.role`, `User.isActive`
- `Product.slug`, `Product.categoryID`, `Product.isActive`, `Product.createdAt`
- `ProductVariant.productID`, `ProductVariant.skuCode`, `ProductVariant.isActive`
- `ProductImage.productID`, `ProductImage.isPrimary`, `ProductImage.color`, `ProductImage.sortOrder`
- `Cart.userID`
- `CartItem.cartID`, `CartItem.variantID`
- `Wishlist.userID`, `Wishlist.productID`
- ``Order.userID``, ``Order.orderNumber``, ``Order.createdAt``, ``Order.orderStatus``
- `OrderItem.orderID`, `OrderItem.variantID`
- `Payment.orderID`, `Payment.paymentStatus`
- `Coupon.code`, `Coupon.isActive`, `Coupon.startsAt`, `Coupon.expiresAt`
- `Inquiry.inquiryStatus`, `Inquiry.createdAt`
- `Review.productID`, `Review.userID`, `Review.orderItemID`, `Review.isApproved`

Relationships that should normally be unique include:

- `User.email`
- `Product.slug`
- `ProductVariant.skuCode`
- `(Wishlist.userID, Wishlist.productID)`
- `Cart.userID` when each user has one cart
- `(CartItem.cartID, CartItem.variantID)`
- ``Order.orderNumber``
- `Payment.orderID` when each order has one payment record
- `(Review.userID, Review.orderItemID)`

Do not apply duplicate or overlapping indexes without checking the real schema. Large index changes should be tested on a copy of the database before production deployment.
