document.addEventListener("DOMContentLoaded", function () {
    const openBtn   = document.querySelector(".review-button");
    const wrapper   = document.querySelector(".course-review-wrapper");
    const closeBtns = document.querySelectorAll(".course-review-wrapper .close");

    if (openBtn && wrapper) {
        openBtn.addEventListener("click", function () {
            wrapper.classList.add("active");
        });
    }

    closeBtns.forEach(btn => {
        btn.addEventListener("click", function (e) {
            e.preventDefault();
            wrapper.classList.remove("active");
        });
    });

});


/*============== js click button ===============*/
document.addEventListener("DOMContentLoaded", function () {

    const stars = document.querySelectorAll(".review-stars li");
    const ratingInput = document.querySelector('input[name="rating"]');

    stars.forEach((star, index) => {
        star.addEventListener("click", function () {
            let rating = index + 1;
            ratingInput.value = rating;
            updateStars(rating);
        });

    });

    function updateStars(rating) {
        stars.forEach((star, i) => {
            if (i < rating) {
                star.classList.add("active");
            } else {
                star.classList.remove("active");
            }
        });
    }

});


/*============== Review Submit ===============*/
document.addEventListener("DOMContentLoaded", function () {

    function showToast(message, type = "success") {

        const colors = {
            success: "#22c55e",
            error: "#ff4d4f",
        };

        Toastify({
            text: message,
            duration: 3000,
            gravity: "bottom",
            position: "center",
            style: { background: colors[type] }
        }).showToast();
    }

    const btn = document.querySelector(".submit-review");
    if (!btn) return;

    btn.addEventListener("click", function (e) {

        e.preventDefault();

        const rating  = document.querySelector('input[name="rating"]')?.value || 0;
        const title   = document.querySelector('input[name="review_title"]')?.value.trim() || '';
        const content = document.querySelector('textarea[name="review_content"]')?.value.trim() || '';
        const nonce   = document.querySelector('[name="lp_review_nonce_field"]')?.value || '';

        // Validation
        if (!title) {
            showToast("Title is required!", "error");
            return;
        }

        if (!content) {
            showToast("Description is required!", "error");
            return;
        }

        if (!rating || rating == 0) {
            showToast("Rating is required!", "error");
            return;
        }

        const data = {
            action: "lp_save_review",
            post_id: btn.dataset.id,
            rating: rating,
            title: title,
            content: content,
            nonce: nonce
        };

        fetch(lp_ajax.url, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: new URLSearchParams(data)
        })
        .then(res => res.json())
        .then(res => {

            if (res.success) {
                showToast("Your review has been submitted and is awaiting approval.", "success");
            } else {
                showToast(res.data.message || "Something went wrong!", "error");
            }

        })
        .catch(() => {
            showToast("AJAX error! Try again.", "error");
        });

    });

});


