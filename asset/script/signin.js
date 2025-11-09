document.getElementById("loginBtn").addEventListener("click", function () {
    const emailInput = document.getElementById("emailInput");
    const emailError = document.getElementById("emailError");
    const passwordInput = document.querySelector("input[name='password']");

    const email = emailInput.value.trim();
    const password = passwordInput.value.trim();

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!emailRegex.test(email)) {
        emailError.textContent = "Invalid email format!";
        return;
    } else {
        emailError.textContent = "";
    }

    // Send data to PHP
    fetch("../backend/_signin.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: `email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`
    })
    .then(response => response.text())
    .then(data => {
    data = data.trim(); // remove spaces/newlines
    if (data === "student") {
        window.location.href = "../dashboard/studentMain.php";
    } else if (data === "admin") {
        window.location.href = "../dashboard/adminMain.html";
    } else if (data === "teacher") {
        window.location.href = "../dashboard/teacherMain.php";
    } else {
        alert(data);
    }
})

    .catch(error => {
        console.error("Login error:", error);
        alert("Something went wrong.");
    });
});
