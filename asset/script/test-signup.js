const container = document.getElementById("container");
const registerBtn = document.getElementById("register");
const loginBtn = document.getElementById("login");

// Variables to store form2 values
let signupData = {
  name: "",
  email: "",
  phone: ""
};

registerBtn.addEventListener("click", () => {
  // Save form2 values before switching
  signupData.name = document.querySelector('.form2 input[name="name"]').value;
  signupData.email = document.querySelector('.form2 input[name="email"]').value;
  signupData.phone = document.querySelector('.form2 input[name="phone"]').value;
  container.classList.add("active");
});

loginBtn.addEventListener("click", () => {
  container.classList.remove("active");
});

document.querySelector('.form1').addEventListener('submit', function (e) {
  e.preventDefault(); // prevent default form submission

  // Use stored values from form2
  const name = signupData.name;
  const email = signupData.email;
  const phone = signupData.phone;

  const password = document.querySelector('.form1 input[name="password"]').value;
  const confirmPassword = document.querySelector('.form1 input[name="confirm_password"]').value;
  const reference = document.querySelector('.form1 input[name="reference"]').value;

  // Simple validation
  if (!name || !email || !phone) {
    alert("Please fill out all fields in the first step before continuing.");
    return;
  }

  // Create a form data object
  const formData = new FormData();
  formData.append('name', name);
  formData.append('email', email);
  formData.append('phone', phone);
  formData.append('password', password);
  formData.append('confirm_password', confirmPassword);
  formData.append('reference', reference);

  // Send to PHP
  fetch('../backend/test-signpu.php', {
    method: 'POST',
    body: formData
  })
    .then(res => res.json())
    .then(data => {
      alert(data.message);
      if (data.status === 'success') {
        window.location.href = 'sigin.html'; // Redirect to login page (same folder as signup.html)
      }
    })
    .catch(err => console.error('Signup failed:', err));
});