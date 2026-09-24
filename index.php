<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NovaCare - Healthcare Appointment</title>
    <style>
        :root{
            --olive:#4b4f2f;
            --dark-olive:#30331ff;
            --butter:#f3d878;
            --light-butter:#f8e9a9;
            --oat:#eee9dc;
            --light-oat:#f85ed;
            --cherry:#7d1f32;
            --dark-cherry:#5d1726;
            --white:#ffffff;
            --black:#202019;
            --gray:#68685f;
        }


        *{
            margin:0;
            padding:0;box-sizing:border-box;
        }
         html{
            scroll-brhavior:

            smooth;
         }

         body{
            font-family:Arial,Helvetica,sans-serif;
            background:var(--light-oat);
            color:var(--black);
            line-height:1.6;
         }

         a{
            text-decoration:none;
            color:inherite;
         }

         .navbar{
            height:82px;
            background:var(--light-oat);
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:0 6%;
            border-bottom:1px solid #ddd8ca;
            position:sticky;
            top:0;
            z-index:100;
         }

         .logo{
            font-size:25px;
            font-weight:bold;
         }

         .logo span{
            display:inline-flex;
            width:34px;
            height:34px;
            background:var(--cherry);
            color:white;
            border-radius:50%;
            align-items:center;
            justify-content:center;
            margin-right:7px;
         }

         .navbar nav{
            display:flex;
            gap:30px;
         }

         .navbar nav a{
            color:#55554e;
            font-size:14px;

         }
         .navbar nav a:hover{
            color:var(--cherry);

         }
         .nav-buttons{
            display:flex;
            align-items:center;
            gap:20px;
         }

         .btn{
            display:inline-bliock;
            padding:14px 23px;
            border-radius:30px;
            font-size:14px;
            font-weight:bold;

         }

         .cherry-btn{
            background:var(--cherry);
            color:white;

         }

         .cherry-btn:hover{
            background:var(--dark-cherry);
         }

         .oat-btn{
            background:var(--oat);
            color:var(--olive);
         }


         .hero{
            min-height:650px;
            padding:80px 7%;
            display:grid;
            grid-template-columns:1fr 1fr;
            align-items:center;
            gap:70px;

         }

         .small-title{
            color:var(--cherry);
            font-size:12px;
            font-weight:bold;
            letter-spacing:1px;
            margin-bottom:20px;
         }

         .hero h1{
            font-family:Georgia,serif;
            font-size:70px;
            color:var(--gray);
            margin-bottom:30px;
         }

         .hero-bottons{
            display:flex;
            gap:12px;
            margin-bottom:30px;
         }

         .rating{
            font-size:13px;
            color:var(--gray);
         }

         .hero-image{
            position:relative;
         }

         .hero-image img{
            width:100%;
            height:480px;
            object-fit:cover;
            border-radius:35px;
         }

         .image-card{
            position:absolute;
            bottom:25px;
            left:25px;
            background:var(--light-oat);
            padding:20px 25px;
            border-radius:18px;
            box-shadow:0 10px 30px rgba(0,0,0,0.12);
            max-width:240px;

         }

         .image-card small{
            color:var(--olive);
            font-weight:bold;

         }
         .image-card h3{
            font-family:Georgia,serif;
            margin:7px 0;

         }

         .image-card p{
            color:var(--gray);
            font-size:13px;
         }

         .care-section{
            padding:100px 7%;
            background:var(--oat);
         }
         .section-heading{
            max-width:700px;
         }

         .section-heading h2{
            font-family:Georgia,serif;
            font-size:50px;
            line-height:1.1;
            color:var(--olive);
            margin-bottom:20px;
         }

         .section-heading p {
            color:var(--grey);
         }
         .search-box{
            margin-top:45px;
            background:var(--light-oat);
            padding:12px;
            border-radius:25px;
            display:grid;
            gridtamplet-columns:2fr 1fr auto;
            gap:10px;
         }

         .search-box input{
            border:none;
            background:white;
            border-radius:18px;
            padding:18px;
            font-size:14px;
            outline:none;

         }
         .search-box button{
            border:none;
            padding:0 28px;
            border-radius:20px;
            background:var(--cherry);
            color:white;
            font-weight:bold;
         }
         .categories{
            display:grid;
            grid-tamplate-columns:repeat(4, 1fr);
            gap:18px;
            margin-top:30px;
         }
         .category-card{
            background:var(--light-oat);
            padding:28px;
            min-height:190px;
            border-radius:20px;
         }
         .icon{
            width:40px;
            height:40px;
            background:var(--light-butter);
            color:var(--cherry);
            border-radius:50%;
            display:flex;
            justify-content:center;
            align-items:center;
            margin-bottom:20px;
         }
         .category-card small{
            color:var(--cherry);
            font-weight:bold;
         }
         .category-card h3{
            font-family:georgia,serif;
             color:var(--olive);
             margin:5px 0 18px;
         }
         .category-card a{
            font-size:13px;
            color:var(--grey);
         }

         .works{
            padding:100px 7%;
         }
         .center{
            margin:auto;
            text-align:center;
         }
         .steps{
            display:grid;
            grid-tamplet-columns:repeat(3, 1fr);
            gap:25px;
            margin-top:50px;
         }
         .step{
            background:white;
            padding:45px 35px;
            border-radius:25px;
            min-height:230px;

         }
         .step.butter{
            background:var(--butter);
         }
         .step span{
             font-family:georgia,serif;
             font-size:35px;
             color:var(--cherry);
         }
         .step h3{
            font-family:georgia,serif;
            font-size:24px;
            margin:20px 0 10px;
            color:var(--olive);

         }
         .step p{
            color:var(--grey);
         }

         .stats{
            background:var(--cherry);
            color:white;
            padding:60px 7%;
            display:grid;
            grid-template-columns:2fr 1fr 1fr 1fr;
            align-items:center;
            gap:30px;
         }
         .stats-title p{
            color:var(--butter);
            font-size:12px;
            font-weight:bold;
         }
         .stats-title h2{
            font-family:georgia,serif;
            font-size:30px;
            max-width:350px;
         }
         .stat h2{
            color:var(--butter);
            font-family:georgia,serif;
            font-size:45px;
         }
         .doctors {
            padding: 100px 7%;

            background: var(--light-oat);
        }

        .doctor-heading {
            display: flex;

            justify-content: space-between;

            align-items: end;

            margin-bottom: 45px;
        }

        .doctor-heading h2 {
            font-family: Georgia, serif;

            font-size: 50px;

            line-height: 1.1;

            color: var(--olive);

            max-width: 500px;

            margin-bottom: 15px;
        }

        .doctor-heading p {
            color: var(--gray);

            max-width: 550px;
        }
        .doctor-add-box {
            min-height: 300px;

            background: var(--oat);

            border: 2px dashed var(--olive);

            border-radius: 25px;

            display: flex;

            flex-direction: column;

            justify-content: center;

            align-items: center;

            text-align: center;

            padding: 40px;
        }

        .add-icon {
            width: 65px;
            height: 65px;

            border-radius: 50%;

            background: var(--cherry);

            color: white;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 35px;

            margin-bottom: 20px;
        }

        .doctor-add-box h3 {
            font-family: Georgia, serif;

            font-size: 28px;

            color: var(--olive);

            margin-bottom: 8px;
        }

        .doctor-add-box p {
            color: var(--gray);

            margin-bottom: 20px;
        }

        .add-doctor-btn {
            border: none;

            background: var(--cherry);

            color: white;

            padding: 13px 25px;

            border-radius: 25px;

            font-weight: bold;

            cursor: pointer;

            font-size: 14px;
        }

        .add-doctor-btn:hover {
            background: var(--dark-cherry);
        }
        
 footer {
            background: var(--dark-olive);

            color: white;

            padding: 60px 7%;

            display: grid;

            grid-template-columns: 2fr 1fr 1fr 1fr;

            gap: 40px;
        }

        .footer-logo {
            font-size: 25px;
            font-weight: bold;
            color: var(--butter);
        }

        footer h4 {
            color: var(--butter);

            margin-bottom: 15px;
        }

        footer a {
            display: block;

            color: #ddd;

            font-size: 14px;

            margin-bottom: 8px;
        }
 @media (max-width: 900px) {

            .navbar nav {
                display: none;
            }

            .hero {
                grid-template-columns: 1fr;
            }

            .categories {
                grid-template-columns: repeat(2, 1fr);
            }

            .steps {
                grid-template-columns: 1fr;
            }

            .stats {
                grid-template-columns: 1fr 1fr;
            }

            .doctor-heading {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }

            .faq {
                grid-template-columns: 1fr;
            }

            footer {
                grid-template-columns: 1fr 1fr;
            }
        }


        @media (max-width: 600px) {

            .hero {
                padding: 60px 5%;
            }

            .hero h1 {
                font-size: 48px;
            }

            .categories {
                grid-template-columns: 1fr;
            }

            .search-box {
                grid-template-columns: 1fr;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .doctor-heading h2 {
                font-size: 38px;
            }

            .cta {
                flex-direction: column;
                align-items: flex-start;
            }

            footer {
                grid-template-columns: 1fr;
            }
        }

    </style>


         
</head>
<body>
    <header class="navbar">
        <div class="logo">
            <span>+</span>NovaCare</div>
            
            <nav>
                <a href="#home">Home</a>
                <a href="#care">Hospitals</a>
                <a href="Doctors">Doctors</a>
                <a href="#works">How It Works</a>
                <a href="#faq">FAQ</a>
                <a href="#contact">Contact</a>
</nav>
<div class="nav-buttons">
    <a href="#" class="login">login</a>
    
    <a href="#appointment" class="btn cherry-btn">Book Appointment</a>
</div>
</header>

<section class="hero" id="home">
    <div class="hero-content">
        <p class="small-title"> CARE, MADE EASIER </p>
        <h1>Better care starts with the right connection.</h1>
        <p class="hero-text" Find trusted hospitals and experienced doctors, comare availability , and book your appointment in a few simple steps.</p>
        <div class="hero-buttons">
            <a href="#Doctors" class="btn cherry-btn"> Find a Doctor </a>
            <a href="#care" class="btn oat-btn"> Browse hospitals</a>
</div class="rating">
    <span>.</span>
    <span>.</span>
    <span>.</span>
    <b>4.9/5</b> from 8000+ cared-for patients </div>
</div>


<div class="hero-image">
    <img src="doctor.jpg" alt="Doctor talking with patient">

    <div class="image-card">
        <small>.AVAILABILITY TODAY </samll>
        <h3>Care when it suits you.</h3>
        <p>128 nearby appointments</p>
</div>
</div>
</section>


<section class="care-section" id="care">
    <div class="section-heading">
        <p class="small-title">. DISCOVER CARE</p>
        <h2>A trusted place for every kind of care.</h2>
        <p> search by specialist,doctor,or hospital, Every provider is verified, so your next step feels informed.</p>
</div>


<div class="search-box">
    <input type="text" placeholder="🔎 speciality,doctor,or condition">
    <input type="text" placeholder="📍 your location">
    <button class="cherry-btn"> Search care</button>
</div>


<div class="categories">
    <div class="category-card">
        <div class="category-card">
    <div class="icon">+</div>
    <small>cardiology</small>
    <h3>Heart care</h3>
     <a href="#">Explore Specialists </a>
    
     
</div>
   <div class="icon">+</div>
        <small>Prdiatrics</small>
        <h3>Growing Families</h3
        <a href="#">Explore Specialists</a>
</div>

<div class="category-card">
    <div class="icon">+</div>
    <small>Orthopedics</small>
  <h3> move with Ease</h3>
  <a href="#">Explore specialists</a>
</div>

<div class="category-card">
    <div class="icon">+</div>
    <small>Primary care</small>
  <h3> Everyday wellness</h3>
  <a href="#">Explore specialists</a>
</div>
</div>
</section>


<section class="work" id="works">
    <div class="section-heading center">
        <p class="small-title">.How It Works</p>
        <h2> from search to seen in three simple steps.</h2>

    <p>NovaCare keeps your healthcare journey clean from the first search to your visit.</p>
</div>

<div class="steps">
    <div class="step">
        <span>01</span>
        <h3>Tell us what you need</h3>
        <p>choose a specialty,symptom,or preferred hospital.</p>
</div>
<div class="step butter">
    <span>02</span>
    <h3>compare trusted care</h3>
    <p> Review Verified profiles,experience,ratings and availability.</p>
</div>
<div class="step">
    <span>03</span>
    <h3>Book with confidence</h3>
    <p> Select a time ,share key details,and receive confirmation.</p>

</div>
</div>
</section>

<sectionj class="stats">
    <div class="stats-title">
        <p>.Care you can count on </p>
        <h2> Human support,backed by a growing care network"</h2>
</div>

<div class="stat">
    <h2>250+</h2>
    <p>Partner Hospitals</p>
</div>

<div class="stat">
    <h2>1,800+</h2>
    <p>verified Doctors</p>
</div>

<div class="stat">
    <h2>98%</h2>
    <p>Booking Satisfaction</p>
</div>
</section>

<section class="Doctors" id="doctors">
    <div class="doctor-heading">
        <div>
            <p class="small-title">.MEET YOUR CARE TEAM</p>
            <h2>Find the right doctor for you.</h2>
            <p> Add and manage doctors based on there speciality,experience,and availability.</p>
</div>

<div class="doctor-add-box">
    <div class="add-icon">+</div>
    <h3>Add a doctor</h3>
    <p> Add doctor information here. when you are ready.</p>
    <button class="add-doctor-btn">+Add Doctor</button>
</div>
</section>


<section class="faq" id="faq">
    <div class="faq-title">
        <p class="small-title">.GOOD TO KNOW</p>
        <h2> Questions deserve clear answers.</h2>
        <p>our care team is here if you need anything beyond these essentials.</p>
</div>
<div class="faq-list">
    <details>
        <summary>
            Is NovaCare free for patients?
            <span>+<span>
</summary>
<p>provider information is reviewed before being displayed on the platform.</p>
</details>

<details>
    <summary>
        Can I reschedule or cancle online?<span>+</span>
</summary>

<p> yes,appointments can be managed through your account.</p>
</details>

<details> 
    <summary>What information do I need to Book?$_COOKIE<span>+</span>
</summary>
<p>
    You generally need your basic contact information and appointment details.</p>
</details>
</div>
</section>


<section class="cta" id="appointment">
    <div>
        <h2> your next care connection is closer than you think.</h2>
        <p>Book an Appointment</a>
</section>

<footer id ="contact">
    <div class="footer-logo">
</span>+</span>NovCare
</div>

<div>
    <h4>Explore</h4>
    <a href="#care">Hospitals</a>
    <a href="#doctors">Doctors</a>
</div>

<div>
    <h4>Contact</h4>
    <a href="#">hello@novacare.com</a>
    <a href="#">+1 800 682 2273 </a>
</div>
</footer>
</body>
</html>










  





    



        

    
